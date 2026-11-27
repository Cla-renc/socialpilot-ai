from __future__ import annotations

from datetime import datetime, timezone
import os
from typing import Any

import httpx

from .news_api import _parse_iso_date
from .rss import validate_feed_url


class InstagramConnector:
    """Instagram Platform Connector via Meta Graph API.

    Uses official Meta Graph API Hashtag Search endpoints when connected to an
    Instagram Professional (Business or Creator) Account.
    If no connected token is available, returns transparent authorization requirement.
    """

    source_key = "instagram"
    source_name = "Instagram Platform API"
    graph_base_url = "https://graph.facebook.com/v22.0"

    def __init__(
        self,
        access_token: str | None = None,
        instagram_account_id: str | None = None,
        client_factory: Any = httpx.AsyncClient,
        timeout_seconds: float = 10,
    ) -> None:
        self.access_token = (
            access_token
            or os.getenv("INSTAGRAM_ACCESS_TOKEN", "")
            or os.getenv("META_ACCESS_TOKEN", "")
        ).strip()
        self.account_id = (
            instagram_account_id
            or os.getenv("INSTAGRAM_ACCOUNT_ID", "")
            or os.getenv("META_INSTAGRAM_ACCOUNT_ID", "")
        ).strip()
        self.client_factory = client_factory
        self.timeout_seconds = timeout_seconds

    @property
    def is_configured(self) -> bool:
        return bool(self.access_token and self.account_id)

    async def discover(
        self,
        queries: list[str],
        days: int = 30,
        market: str | None = None,
    ) -> tuple[list[dict[str, Any]], int, list[str]]:
        del days, market
        if not self.is_configured:
            return [], 0, [
                "Instagram Graph API discovery requires a connected Instagram Professional "
                "(Business or Creator) account and Page authorization."
            ]

        if not queries:
            return [], 0, ["No search queries or hashtags provided for Instagram research."]

        results: list[dict[str, Any]] = []
        errors: list[str] = []
        successful_requests = 0

        # Extract hashtag query (clean spaces and special characters)
        raw_query = queries[0].replace("#", "").strip().split()[0]
        if not raw_query:
            return [], 0, ["Invalid hashtag format for Instagram discovery."]

        async with self.client_factory(
            timeout=self.timeout_seconds,
            follow_redirects=False,
        ) as client:
            try:
                # 1. Search for hashtag ID
                search_url = f"{self.graph_base_url}/ig_hashtag_search"
                search_res = await client.get(
                    search_url,
                    params={
                        "user_id": self.account_id,
                        "q": raw_query,
                        "access_token": self.access_token,
                    },
                )
                if search_res.status_code == 400 or search_res.status_code == 401:
                    errors.append("Instagram authorization expired or missing permissions.")
                    return [], 0, errors
                if search_res.status_code == 429:
                    errors.append("Instagram Graph API rate limit exceeded.")
                    return [], 0, errors
                search_res.raise_for_status()
                hashtag_data = search_res.json().get("data", [])
                if not hashtag_data:
                    return [], 0, [f"No Instagram hashtag ID found for #{raw_query}."]

                hashtag_id = hashtag_data[0]["id"]
                successful_requests += 1

                # 2. Retrieve top media for the hashtag
                media_url = f"{self.graph_base_url}/{hashtag_id}/top_media"
                media_res = await client.get(
                    media_url,
                    params={
                        "user_id": self.account_id,
                        "fields": "id,caption,media_type,permalink,like_count,comments_count,timestamp",
                        "access_token": self.access_token,
                    },
                )
                media_res.raise_for_status()
                media_items = media_res.json().get("data", [])
                successful_requests += 1

                for item in media_items[:25]:
                    caption = (item.get("caption") or "").strip()
                    permalink = item.get("permalink") or ""
                    if not caption or not validate_feed_url(permalink):
                        continue

                    likes = int(item.get("like_count") or 0)
                    comments = int(item.get("comments_count") or 0)
                    pub_date = _parse_iso_date(item.get("timestamp"))

                    title = caption.split("\n")[0][:120].strip() or f"#{raw_query} on Instagram"

                    results.append({
                        "source_key": self.source_key,
                        "source_name": f"{self.source_name} (#{raw_query})",
                        "title": title[:500],
                        "url": permalink,
                        "published_at": pub_date,
                        "summary": caption[:1200] if len(caption) > 120 else None,
                        "evidence": f"{caption[:600]} (Engagement: {likes:,} likes, {comments:,} comments)",
                        "metrics": {
                            "likes": likes,
                            "comments": comments,
                        },
                    })
            except httpx.HTTPStatusError as ex:
                errors.append(f"Instagram Graph API returned HTTP {ex.response.status_code}.")
            except httpx.TimeoutException:
                errors.append("Instagram Graph API request timed out.")
            except Exception as ex:
                errors.append(f"Instagram discovery failed: {str(ex)[:200]}")

        return results, successful_requests, errors
