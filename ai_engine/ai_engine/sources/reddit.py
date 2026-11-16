from __future__ import annotations

import base64
from datetime import datetime, timezone
import os
from typing import Any

import httpx

from .rss import validate_feed_url


class RedditConnector:
    source_key = "reddit"
    source_name = "Reddit API"
    token_url = "https://www.reddit.com/api/v1/access_token"
    search_url = "https://oauth.reddit.com/search"

    def __init__(
        self,
        client_id: str | None = None,
        client_secret: str | None = None,
        client_factory: Any = httpx.AsyncClient,
        timeout_seconds: float = 10,
    ) -> None:
        self.client_id = (client_id or os.getenv("REDDIT_CLIENT_ID", "")).strip()
        self.client_secret = (client_secret or os.getenv("REDDIT_CLIENT_SECRET", "")).strip()
        self.client_factory = client_factory
        self.timeout_seconds = timeout_seconds
        self.user_agent = os.getenv(
            "REDDIT_USER_AGENT",
            "SocialPilotAI-TrendResearch/1.0 (by /u/SocialPilotBot)",
        )

    @property
    def is_configured(self) -> bool:
        return bool(self.client_id and self.client_secret)

    def _time_filter(self, days: int) -> str:
        if days <= 1:
            return "day"
        if days <= 7:
            return "week"
        if days <= 31:
            return "month"
        return "year"

    async def _get_access_token(self, client: httpx.AsyncClient) -> tuple[str | None, str | None]:
        credentials = f"{self.client_id}:{self.client_secret}".encode("utf-8")
        auth_header = f"Basic {base64.b64encode(credentials).decode('ascii')}"

        response = await client.post(
            self.token_url,
            data={"grant_type": "client_credentials"},
            headers={
                "Authorization": auth_header,
                "User-Agent": self.user_agent,
            },
        )
        if response.status_code == 401:
            return None, "Invalid Reddit client ID or client secret."
        if response.status_code == 429:
            return None, "Reddit API token rate limit exceeded."
        response.raise_for_status()

        token_data = response.json()
        token = token_data.get("access_token")
        if not token:
            return None, "Reddit authentication response omitted access token."
        return token, None

    async def discover(
        self,
        queries: list[str],
        days: int = 30,
        market: str | None = None,
    ) -> tuple[list[dict[str, Any]], int, list[str]]:
        del market
        if not self.is_configured:
            return [], 0, ["Reddit API is not configured. Set REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET."]

        if not queries:
            return [], 0, ["No search queries provided for Reddit research."]

        query_str = " OR ".join(f'"{q.strip()}"' for q in queries[:4] if q.strip())
        if not query_str:
            query_str = queries[0]

        results: list[dict[str, Any]] = []
        errors: list[str] = []
        successful_requests = 0

        async with self.client_factory(
            timeout=self.timeout_seconds,
            follow_redirects=False,
        ) as client:
            try:
                token, auth_error = await self._get_access_token(client)
                if auth_error or not token:
                    return [], 0, [auth_error or "Reddit authorization failed."]

                params = {
                    "q": query_str[:400],
                    "sort": "relevance",
                    "t": self._time_filter(days),
                    "limit": 50,
                    "type": "link",
                }
                headers = {
                    "Authorization": f"Bearer {token}",
                    "User-Agent": self.user_agent,
                }

                response = await client.get(self.search_url, params=params, headers=headers)
                if response.status_code == 429:
                    reset_seconds = response.headers.get("x-ratelimit-reset", "unknown")
                    errors.append(f"Reddit API rate limit exceeded. Reset in {reset_seconds}s.")
                    return [], 0, errors

                response.raise_for_status()
                data = response.json()
                successful_requests += 1

                posts = (data.get("data") or {}).get("children") or []
                for child in posts[:100]:
                    post_data = child.get("data") or {}
                    title = (post_data.get("title") or "").strip()
                    permalink = post_data.get("permalink") or ""
                    post_url = f"https://www.reddit.com{permalink}" if permalink else (post_data.get("url") or "")

                    if not title or not validate_feed_url(post_url):
                        continue

                    subreddit = post_data.get("subreddit_name_prefixed") or f"r/{post_data.get('subreddit', 'reddit')}"
                    created_utc = post_data.get("created_utc")
                    published_at = (
                        datetime.fromtimestamp(created_utc, tz=timezone.utc)
                        if created_utc
                        else None
                    )
                    selftext = (post_data.get("selftext") or "").strip()[:1200]
                    score = int(post_data.get("score") or 0)
                    num_comments = int(post_data.get("num_comments") or 0)

                    results.append({
                        "source_key": self.source_key,
                        "source_name": f"{self.source_name} ({subreddit})",
                        "title": title[:500],
                        "url": post_url,
                        "published_at": published_at,
                        "summary": selftext or None,
                        "evidence": selftext or title,
                        "metrics": {
                            "likes": score,
                            "comments": num_comments,
                        },
                    })
            except httpx.HTTPStatusError as error:
                errors.append(f"Reddit API returned HTTP {error.response.status_code}.")
            except httpx.TimeoutException:
                errors.append("Reddit API request timed out.")
            except (httpx.HTTPError, ValueError) as ex:
                errors.append(f"Reddit API search failed: {str(ex)[:200]}")

        return results, successful_requests, errors
