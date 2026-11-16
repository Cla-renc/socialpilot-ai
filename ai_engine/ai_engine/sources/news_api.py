from __future__ import annotations

from datetime import datetime, timedelta, timezone
import os
from typing import Any
from urllib.parse import urlparse

import httpx

from .rss import validate_feed_url


def _parse_iso_date(value: str | None) -> datetime | None:
    if not value:
        return None
    try:
        parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
        if parsed.tzinfo is None:
            return parsed.replace(tzinfo=timezone.utc)
        return parsed.astimezone(timezone.utc)
    except (ValueError, TypeError):
        return None


class NewsApiConnector:
    source_key = "news_api"
    source_name = "News API"
    base_url = "https://newsapi.org/v2/everything"

    def __init__(
        self,
        api_key: str | None = None,
        client_factory: Any = httpx.AsyncClient,
        timeout_seconds: float = 10,
    ) -> None:
        self.api_key = (
            api_key
            or os.getenv("NEWSAPI_API_KEY", "")
            or os.getenv("NEWS_API_KEY", "")
        ).strip()
        self.client_factory = client_factory
        self.timeout_seconds = timeout_seconds

    @property
    def is_configured(self) -> bool:
        return bool(self.api_key)

    async def discover(
        self,
        queries: list[str],
        days: int = 30,
        market: str | None = None,
    ) -> tuple[list[dict[str, Any]], int, list[str]]:
        if not self.is_configured:
            return [], 0, ["News API is not configured. Set NEWSAPI_API_KEY (or legacy NEWS_API_KEY) in the environment."]

        if not queries:
            return [], 0, ["No search queries provided for News API research."]

        cutoff = datetime.now(timezone.utc) - timedelta(days=days)
        from_str = cutoff.strftime("%Y-%m-%d")

        query_str = " OR ".join(f'"{q.strip()}"' for q in queries[:5] if q.strip())
        if not query_str:
            query_str = queries[0]
        if market:
            query_str = f"({query_str}) AND {market.strip()}"

        results: list[dict[str, Any]] = []
        errors: list[str] = []
        successful_requests = 0

        params = {
            "q": query_str[:500],
            "from": from_str,
            "sortBy": "relevancy",
            "pageSize": 50,
            "language": "en",
        }
        headers = {
            "X-Api-Key": self.api_key,
            "User-Agent": "SocialPilotAI-TrendResearch/1.0",
        }

        try:
            async with self.client_factory(
                timeout=self.timeout_seconds,
                follow_redirects=False,
            ) as client:
                async with client.stream("GET", self.base_url, params=params, headers=headers) as response:
                    if response.status_code == 401:
                        errors.append("Invalid or unauthorized News API key.")
                        return [], 0, errors
                    if response.status_code == 429:
                        errors.append("News API rate limit exceeded.")
                        return [], 0, errors
                    response.raise_for_status()

                    length = int(response.headers.get("content-length", "0") or 0)
                    if length > 2_000_000:
                        raise ValueError("News API response exceeded the 2 MB limit.")

                    chunks = []
                    size = 0
                    async for chunk in response.aiter_bytes():
                        size += len(chunk)
                        if size > 2_000_000:
                            raise ValueError("News API response exceeded the 2 MB limit.")
                        chunks.append(chunk)

                    data = httpx.Response(200, content=b"".join(chunks)).json()
                    articles = data.get("articles") or []
                    successful_requests += 1

                    for item in articles[:100]:
                        title = (item.get("title") or "").strip()
                        url = (item.get("url") or "").strip()
                        if not title or not validate_feed_url(url) or title == "[Removed]":
                            continue

                        source_info = item.get("source") or {}
                        publisher = source_info.get("name") or urlparse(url).hostname or "News Provider"
                        published_at = _parse_iso_date(item.get("publishedAt"))
                        description = (item.get("description") or item.get("content") or "").strip()[:1200]

                        results.append({
                            "source_key": self.source_key,
                            "source_name": f"{self.source_name} ({publisher})",
                            "title": title[:500],
                            "url": url,
                            "published_at": published_at,
                            "summary": description or None,
                            "evidence": description or title,
                            "metrics": {},
                        })
        except httpx.HTTPStatusError as error:
            errors.append(f"News API returned HTTP {error.response.status_code}.")
        except httpx.TimeoutException:
            errors.append("News API request timed out.")
        except (httpx.HTTPError, ValueError) as ex:
            errors.append(f"News API retrieval failed: {str(ex)[:200]}")

        return results, successful_requests, errors
