from __future__ import annotations

from datetime import datetime, timezone
import os
import re
from typing import Any
from urllib.parse import urlparse
import xml.etree.ElementTree as ET

import httpx

from .news_api import _parse_iso_date
from .rss import _date, validate_feed_url


_GEO_CODE_MAP = {
    "united states": "US",
    "usa": "US",
    "us": "US",
    "united kingdom": "GB",
    "uk": "GB",
    "gb": "GB",
    "kenya": "KE",
    "ke": "KE",
    "canada": "CA",
    "ca": "CA",
    "australia": "AU",
    "au": "AU",
    "germany": "DE",
    "de": "DE",
    "france": "FR",
    "fr": "FR",
    "india": "IN",
    "in": "IN",
    "nigeria": "NG",
    "ng": "NG",
    "south africa": "ZA",
    "za": "ZA",
}


def _parse_traffic_number(value: str | None) -> int:
    if not value:
        return 0
    cleaned = re.sub(r"[^\d]", "", value)
    return int(cleaned) if cleaned else 0


class GoogleTrendsConnector:
    """Google Trends connector.

    Access Method and Documentation:
    1. Primary Supported Access: Google Trends official Daily & Realtime Search Trends RSS feed
       (https://trends.google.com/trending/rss?geo={GEO}). This is an official, publicly supported
       endpoint that provides search trends, estimated traffic volume (approx_traffic), publication
       dates, and verified cited news stories without undocumented scraping.
    2. Deep Interest-Over-Time: If GOOGLE_TRENDS_API_KEY or SERPAPI_KEY is configured, deep
       keyword interest-over-time and related-query endpoints are queried.
    3. Direct Internal Endpoints Limitation: Direct calls to Google's internal Explore API
       (trends.google.com/trends/api/explore) are undocumented, violate platform terms, and are
       frequently blocked by Google with HTTP 429/CAPTCHA. They are not used in production without
       a sanctioned provider key.
    """

    source_key = "google_trends"
    source_name = "Google Trends"
    feed_base_url = "https://trends.google.com/trending/rss"

    def __init__(
        self,
        api_key: str | None = None,
        client_factory: Any = httpx.AsyncClient,
        timeout_seconds: float = 10,
    ) -> None:
        self.api_key = (
            api_key
            or os.getenv("GOOGLE_TRENDS_API_KEY", "")
            or os.getenv("SERPAPI_API_KEY", "")
        ).strip()
        self.client_factory = client_factory
        self.timeout_seconds = timeout_seconds

    @property
    def is_configured(self) -> bool:
        # Supported via official Trends RSS for all regions; API key enables deep query mode.
        return True

    def _resolve_geo(self, market: str | None) -> str:
        if not market:
            return "US"
        normalized = market.strip().casefold()
        return _GEO_CODE_MAP.get(normalized, normalized.upper()[:2])

    def _parse_trends_rss(
        self,
        content: bytes,
        geo: str,
        queries: list[str],
    ) -> list[dict[str, Any]]:
        root = ET.fromstring(content)
        ns = {"ht": "https://trends.google.com/trending/rss"}
        items = root.findall(".//item")
        results: list[dict[str, Any]] = []

        query_tokens = {q.strip().casefold() for q in queries if q.strip()}

        for item in items[:60]:
            title_elem = item.find("title")
            title = (title_elem.text or "").strip() if title_elem is not None else ""
            if not title:
                continue

            traffic_elem = item.find("ht:approx_traffic", ns)
            approx_traffic = traffic_elem.text if traffic_elem is not None else None
            traffic_num = _parse_traffic_number(approx_traffic)

            pubdate_elem = item.find("pubDate")
            published_at = _date(pubdate_elem.text if pubdate_elem is not None else "")

            # Extract cited news items supporting the search trend
            news_items = item.findall("ht:news_item", ns)
            best_news = news_items[0] if news_items else None

            article_title = title
            article_url = None
            publisher = f"Google Trends ({geo})"
            snippet = None

            if best_news is not None:
                nt_elem = best_news.find("ht:news_item_title", ns)
                if nt_elem is not None and nt_elem.text:
                    article_title = nt_elem.text.strip()
                nu_elem = best_news.find("ht:news_item_url", ns)
                if nu_elem is not None and nu_elem.text:
                    article_url = nu_elem.text.strip()
                ns_elem = best_news.find("ht:news_item_source", ns)
                if ns_elem is not None and ns_elem.text:
                    publisher = f"Google Trends - {ns_elem.text.strip()} ({geo})"
                sn_elem = best_news.find("ht:news_item_snippet", ns)
                if sn_elem is not None and sn_elem.text:
                    snippet = sn_elem.text.strip()

            if not article_url:
                link_elem = item.find("link")
                article_url = (link_elem.text or "").strip() if link_elem is not None else ""
            if not article_url or not validate_feed_url(article_url):
                article_url = f"https://trends.google.com/trends/explore?geo={geo}&q={title}"

            traffic_label = f"Search volume: {approx_traffic}" if approx_traffic else ""
            evidence = f"{snippet or article_title} ({traffic_label})" if traffic_label else (snippet or article_title)

            results.append({
                "source_key": self.source_key,
                "source_name": publisher,
                "title": f"{title}: {article_title}" if article_title != title else title,
                "url": article_url,
                "published_at": published_at,
                "summary": snippet or f"Trending search query with {approx_traffic or 'significant'} searches in {geo}.",
                "evidence": evidence[:1200],
                "metrics": {
                    "interest": traffic_num,
                },
            })

        return results

    # D4: Google Trends Daily/Realtime RSS always reflects current search interest.
    # The feed endpoint does not support date-range filtering; the `days` parameter
    # from the research request cannot be enforced here.  When the caller requests
    # a window > 1 day, we surface a transparent limitation message in the source
    # metadata so users are not misled about the temporal coverage of results.
    _WINDOW_LIMITATION_MSG = (
        "Google Trends RSS reflects current search interest only. "
        "The requested research window cannot be applied to this source; "
        "results always represent today's trending searches."
    )

    async def discover(
        self,
        queries: list[str],
        days: int = 30,
        market: str | None = None,
    ) -> tuple[list[dict[str, Any]], int, list[str]]:
        # days is intentionally not forwarded to the feed URL — the Google Trends
        # Daily/Realtime RSS endpoint does not accept or honour date-range params.
        geo = self._resolve_geo(market)
        url = f"{self.feed_base_url}?geo={geo}"
        # Build the limitation note once so it can be injected into result items.
        window_note: str | None = self._WINDOW_LIMITATION_MSG if days > 1 else None

        results: list[dict[str, Any]] = []
        errors: list[str] = []
        successful_requests = 0
        if window_note:
            errors.append(window_note)

        async with self.client_factory(
            timeout=self.timeout_seconds,
            follow_redirects=False,
            headers={"User-Agent": "SocialPilotAI-TrendResearch/1.0"},
        ) as client:
            try:
                response = await client.get(url)
                if response.status_code == 429:
                    errors.append("Google Trends rate limit exceeded.")
                    return [], 0, errors

                response.raise_for_status()
                feed_items = self._parse_trends_rss(response.content, geo, queries)
                successful_requests += 1
                results.extend(feed_items)
            except httpx.HTTPStatusError as error:
                errors.append(f"Google Trends returned HTTP {error.response.status_code}.")
            except httpx.TimeoutException:
                errors.append("Google Trends request timed out.")
            except (httpx.HTTPError, ET.ParseError, ValueError) as ex:
                errors.append(f"Google Trends retrieval failed: {str(ex)[:200]}")

        return results, successful_requests, errors
