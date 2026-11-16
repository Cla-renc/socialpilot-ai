from __future__ import annotations

from datetime import datetime, timedelta, timezone
import os
from typing import Any

import httpx

from .news_api import _parse_iso_date
from .rss import validate_feed_url


class YouTubeConnector:
    source_key = "youtube"
    source_name = "YouTube Data API"
    search_url = "https://www.googleapis.com/youtube/v3/search"
    videos_url = "https://www.googleapis.com/youtube/v3/videos"

    def __init__(
        self,
        api_key: str | None = None,
        client_factory: Any = httpx.AsyncClient,
        timeout_seconds: float = 10,
    ) -> None:
        self.api_key = (
            api_key
            or os.getenv("YOUTUBE_DATA_API_KEY", "")
            or os.getenv("YOUTUBE_API_KEY", "")
        ).strip()
        self.client_factory = client_factory
        self.timeout_seconds = timeout_seconds

    @property
    def is_configured(self) -> bool:
        return bool(self.api_key)

    async def _fetch_video_statistics(
        self,
        client: httpx.AsyncClient,
        video_ids: list[str],
    ) -> dict[str, dict[str, int]]:
        if not video_ids:
            return {}

        stats_map: dict[str, dict[str, int]] = {}
        # Batch in chunks of 50 per YouTube API limit
        for i in range(0, len(video_ids), 50):
            batch = video_ids[i:i + 50]
            try:
                response = await client.get(
                    self.videos_url,
                    params={
                        "part": "statistics",
                        "id": ",".join(batch),
                        "key": self.api_key,
                    },
                )
                if response.status_code == 200:
                    data = response.json()
                    for item in data.get("items") or []:
                        v_id = item.get("id")
                        stats = item.get("statistics") or {}
                        stats_map[v_id] = {
                            "views": int(stats.get("viewCount") or 0),
                            "likes": int(stats.get("likeCount") or 0),
                            "comments": int(stats.get("commentCount") or 0),
                        }
            except (httpx.HTTPError, ValueError):
                pass
        return stats_map

    async def discover(
        self,
        queries: list[str],
        days: int = 30,
        market: str | None = None,
    ) -> tuple[list[dict[str, Any]], int, list[str]]:
        if not self.is_configured:
            return [], 0, ["YouTube Data API is not configured. Set YOUTUBE_API_KEY (or legacy YOUTUBE_DATA_API_KEY) in the environment."]

        if not queries:
            return [], 0, ["No search queries provided for YouTube research."]

        cutoff = datetime.now(timezone.utc) - timedelta(days=days)
        published_after = cutoff.strftime("%Y-%m-%dT%H:%M:%SZ")

        query_str = queries[0].strip()
        if market:
            query_str = f"{query_str} {market.strip()}"

        results: list[dict[str, Any]] = []
        errors: list[str] = []
        successful_requests = 0

        async with self.client_factory(
            timeout=self.timeout_seconds,
            follow_redirects=False,
        ) as client:
            try:
                search_params = {
                    "part": "snippet",
                    "q": query_str[:300],
                    "type": "video",
                    "publishedAfter": published_after,
                    "order": "relevance",
                    "maxResults": 25,
                    "key": self.api_key,
                }
                response = await client.get(self.search_url, params=search_params)

                if response.status_code == 403:
                    body = response.text
                    if "quotaExceeded" in body or "dailyLimitExceeded" in body:
                        errors.append("YouTube Data API quota exceeded.")
                    else:
                        errors.append("YouTube Data API access forbidden or API key invalid.")
                    return [], 0, errors

                if response.status_code == 400:
                    errors.append("Invalid YouTube API request parameters or API key.")
                    return [], 0, errors

                response.raise_for_status()
                data = response.json()
                items = data.get("items") or []
                successful_requests += 1

                video_ids = [
                    item["id"]["videoId"]
                    for item in items
                    if item.get("id", {}).get("videoId")
                ]

                # Retrieve engagement statistics to distinguish search relevance from popularity
                stats_map = await self._fetch_video_statistics(client, video_ids)

                for item in items:
                    v_id = item.get("id", {}).get("videoId")
                    if not v_id:
                        continue
                    url = f"https://www.youtube.com/watch?v={v_id}"
                    if not validate_feed_url(url):
                        continue

                    snippet = item.get("snippet") or {}
                    title = (snippet.get("title") or "").strip()
                    if not title:
                        continue

                    channel_title = snippet.get("channelTitle") or "YouTube Channel"
                    published_at = _parse_iso_date(snippet.get("publishedAt"))
                    description = (snippet.get("description") or "").strip()[:1200]
                    stats = stats_map.get(v_id, {})

                    views = stats.get("views", 0)
                    likes = stats.get("likes", 0)
                    comments = stats.get("comments", 0)

                    # Build evidence summary with statistics
                    evidence_parts = []
                    if description:
                        evidence_parts.append(description)
                    if views > 0 or likes > 0:
                        evidence_parts.append(f"Engagement: {views:,} views, {likes:,} likes, {comments:,} comments.")

                    results.append({
                        "source_key": self.source_key,
                        "source_name": f"{self.source_name} ({channel_title})",
                        "title": title[:500],
                        "url": url,
                        "published_at": published_at,
                        "summary": description or None,
                        "evidence": " ".join(evidence_parts) or title,
                        "metrics": {
                            "views": views,
                            "likes": likes,
                            "comments": comments,
                        },
                    })
            except httpx.HTTPStatusError as error:
                errors.append(f"YouTube Data API returned HTTP {error.response.status_code}.")
            except httpx.TimeoutException:
                errors.append("YouTube Data API request timed out.")
            except (httpx.HTTPError, ValueError) as ex:
                errors.append(f"YouTube Data API retrieval failed: {str(ex)[:200]}")

        return results, successful_requests, errors
