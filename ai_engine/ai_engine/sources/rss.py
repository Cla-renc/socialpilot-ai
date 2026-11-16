from __future__ import annotations

from datetime import datetime, timezone
from email.utils import parsedate_to_datetime
import ipaddress
import re
from typing import Any
from urllib.parse import urlparse
import xml.etree.ElementTree as ET

import httpx


def validate_feed_url(url: str) -> bool:
    parsed = urlparse(url)
    if parsed.scheme not in {"https", "http"} or not parsed.hostname:
        return False
    hostname = parsed.hostname.casefold()
    if hostname == "localhost" or hostname.endswith(".localhost") or hostname.endswith(".local"):
        return False
    try:
        address = ipaddress.ip_address(hostname)
    except ValueError:
        return True
    return address.is_global


def _text(element: ET.Element, *names: str) -> str:
    for child in element.iter():
        local_name = child.tag.rsplit("}", 1)[-1].casefold()
        if local_name in names and child.text:
            return child.text.strip()
    return ""


def _entry_link(entry: ET.Element) -> str:
    for child in entry:
        if child.tag.rsplit("}", 1)[-1].casefold() == "link":
            href = child.attrib.get("href")
            if href:
                return href.strip()
            return (child.text or "").strip()
    return ""


def _date(value: str) -> datetime | None:
    if not value:
        return None
    try:
        parsed = parsedate_to_datetime(value)
    except (TypeError, ValueError, OverflowError):
        try:
            parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
        except ValueError:
            return None
    if parsed.tzinfo is None:
        return parsed.replace(tzinfo=timezone.utc)
    return parsed.astimezone(timezone.utc)


def parse_feed(content: bytes, source_url: str, source_name: str) -> list[dict[str, Any]]:
    root = ET.fromstring(content)
    channel_name = _text(root, "title") or source_name
    entries = [
        item for item in root.iter()
        if item.tag.rsplit("}", 1)[-1].casefold() in {"item", "entry"}
    ]
    results = []
    for item in entries[:100]:
        title = _text(item, "title")
        link = _entry_link(item)
        if not title or not validate_feed_url(link):
            continue
        summary = _text(item, "description", "summary", "encoded", "content")
        summary = re.sub(r"<[^>]*>", " ", summary)
        summary = " ".join(summary.split())[:1200]
        published = _text(item, "pubdate", "published", "updated", "date")
        results.append({
            "source_key": "rss",
            "source_name": channel_name,
            "title": title[:500],
            "url": link,
            "published_at": _date(published),
            "summary": summary or None,
            "evidence": summary or title,
            "metrics": {},
        })
    return results


class RssConnector:
    source_key = "rss"
    source_name = "Configured RSS feeds"

    def __init__(
        self,
        feed_urls: list[str],
        client_factory: Any = httpx.AsyncClient,
        timeout_seconds: float = 10,
    ) -> None:
        self.feed_urls = [url for url in feed_urls if validate_feed_url(url)]
        self.client_factory = client_factory
        self.timeout_seconds = timeout_seconds

    async def search(self, queries: list[str], days: int, market: str | None = None) -> list[dict[str, Any]]:
        items, _, _ = await self.discover(queries, days, market)
        return items

    async def discover(self, queries: list[str], days: int, market: str | None = None) -> tuple[list[dict[str, Any]], int, list[str]]:
        del queries, market
        cutoff = datetime.now(timezone.utc).timestamp() - days * 86400
        results: list[dict[str, Any]] = []
        successful_feeds = 0
        errors: list[str] = []
        async with self.client_factory(
            timeout=self.timeout_seconds,
            follow_redirects=False,
            headers={"User-Agent": "SocialPilotAI-TrendResearch/1.0"},
        ) as client:
            for url in self.feed_urls:
                try:
                    async with client.stream("GET", url) as response:
                        response.raise_for_status()
                        length = int(response.headers.get("content-length", "0") or 0)
                        if length > 2_000_000:
                            raise ValueError("RSS feed exceeded the 2 MB response limit.")
                        chunks = []
                        size = 0
                        async for chunk in response.aiter_bytes():
                            size += len(chunk)
                            if size > 2_000_000:
                                raise ValueError("RSS feed exceeded the 2 MB response limit.")
                            chunks.append(chunk)
                        feed_results = parse_feed(b"".join(chunks), url, urlparse(url).hostname or "RSS feed")
                        successful_feeds += 1
                        results.extend(feed_results)
                except httpx.HTTPStatusError as error:
                    errors.append(f"An RSS feed returned HTTP {error.response.status_code}.")
                except (httpx.HTTPError, ET.ParseError, ValueError):
                    errors.append("An RSS feed could not be fetched or parsed.")
                    continue
        recent_results = [
            item for item in results
            if item["published_at"] is None or item["published_at"].timestamp() >= cutoff
        ]
        return recent_results, successful_feeds, errors
