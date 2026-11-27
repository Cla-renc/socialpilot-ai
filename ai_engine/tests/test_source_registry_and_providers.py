from __future__ import annotations

from datetime import datetime, timezone
import unittest
from unittest.mock import AsyncMock, MagicMock, patch

import httpx

from ai_engine.research import ResearchOrchestrator
from ai_engine.source_registry import (
    AVAILABILITY_AUTH_REQUIRED,
    AVAILABILITY_CONFIG_REQUIRED,
    AVAILABILITY_OPERATIONAL,
    AVAILABILITY_RESTRICTED,
    SourceRegistry,
)
from ai_engine.sources.facebook import FacebookConnector
from ai_engine.sources.google_trends import GoogleTrendsConnector
from ai_engine.sources.instagram import InstagramConnector
from ai_engine.sources.news_api import NewsApiConnector
from ai_engine.sources.reddit import RedditConnector
from ai_engine.sources.rss import RssConnector
from ai_engine.sources.tiktok import TikTokConnector
from ai_engine.sources.x import XConnector
from ai_engine.sources.youtube import YouTubeConnector


class SourceRegistryTests(unittest.TestCase):
    def setUp(self):
        self.registry = SourceRegistry()

    def test_all_expected_providers_registered(self):
        descriptors = self.registry.all()
        keys = {d.source_key for d in descriptors}
        expected = {
            "google_trends",
            "rss",
            "youtube",
            "reddit",
            "instagram",
            "tiktok",
            "facebook",
            "x",
            "news_api",
        }
        self.assertEqual(keys, expected)
        self.assertEqual(len(descriptors), 9)

    def test_restricted_sources_are_never_marked_operational(self):
        for key in ["tiktok", "facebook", "x"]:
            descriptor = self.registry.get(key)
            self.assertIsNotNone(descriptor)
            self.assertEqual(descriptor.availability, AVAILABILITY_RESTRICTED)
            self.assertFalse(descriptor.is_operational)
            self.assertIsNotNone(descriptor.limitation_message)

    def test_instagram_requires_authorization_by_default(self):
        descriptor = self.registry.get("instagram")
        self.assertIsNotNone(descriptor)
        self.assertIn(descriptor.availability, [AVAILABILITY_AUTH_REQUIRED, AVAILABILITY_OPERATIONAL])
        self.assertTrue(descriptor.requires_auth)

    def test_google_trends_is_operational_at_zero_cost(self):
        descriptor = self.registry.get("google_trends")
        self.assertIsNotNone(descriptor)
        self.assertEqual(descriptor.availability, AVAILABILITY_OPERATIONAL)
        self.assertTrue(descriptor.is_operational)
        self.assertFalse(descriptor.requires_auth)
        self.assertFalse(descriptor.requires_paid)

    def test_status_payload_returns_structured_metadata(self):
        payload = self.registry.get_status_payload()
        self.assertEqual(len(payload), 9)
        for item in payload:
            self.assertIn("source_key", item)
            self.assertIn("platform", item)
            self.assertIn("availability", item)
            self.assertIn("is_operational", item)


class ProviderConnectorTests(unittest.IsolatedAsyncioTestCase):
    async def test_tiktok_connector_reports_academic_restriction(self):
        connector = TikTokConnector()
        self.assertFalse(connector.is_configured)
        results, count, errors = await connector.discover(["ai tools"])
        self.assertEqual(results, [])
        self.assertEqual(count, 0)
        self.assertTrue(any("TikTok Research API is restricted" in e for e in errors))

    async def test_facebook_connector_reports_deprecated_feed_restriction(self):
        connector = FacebookConnector()
        self.assertFalse(connector.is_configured)
        results, count, errors = await connector.discover(["hiking"])
        self.assertEqual(results, [])
        self.assertEqual(count, 0)
        self.assertTrue(any("Facebook Public Feed API was deprecated" in e for e in errors))

    async def test_x_connector_reports_paid_tier_restriction(self):
        connector = XConnector()
        self.assertFalse(connector.is_configured)
        results, count, errors = await connector.discover(["tech"])
        self.assertEqual(results, [])
        self.assertEqual(count, 0)
        self.assertTrue(any("X API v2 requires a paid Basic subscription" in e for e in errors))

    async def test_instagram_connector_unconfigured_reports_authorization_required(self):
        connector = InstagramConnector(access_token="", instagram_account_id="")
        self.assertFalse(connector.is_configured)
        results, count, errors = await connector.discover(["#hiking"])
        self.assertEqual(results, [])
        self.assertEqual(count, 0)
        self.assertTrue(any("Instagram Graph API discovery requires a connected" in e for e in errors))

    async def test_instagram_connector_configured_discovers_media(self):
        def handler(request: httpx.Request) -> httpx.Response:
            url_str = str(request.url)
            if "ig_hashtag_search" in url_str:
                return httpx.Response(200, json={"data": [{"id": "17841400"}]})
            if "top_media" in url_str:
                return httpx.Response(200, json={
                    "data": [
                        {
                            "id": "media_123",
                            "caption": "Top Trail Running Adventures 2026 #trailrunning",
                            "media_type": "IMAGE",
                            "permalink": "https://instagram.com/p/abc12345",
                            "like_count": 1420,
                            "comments_count": 88,
                            "timestamp": "2026-11-26T12:00:00+00:00",
                        }
                    ]
                })
            return httpx.Response(404)

        transport = httpx.MockTransport(handler)
        client_factory = lambda **kw: httpx.AsyncClient(transport=transport, **kw)

        connector = InstagramConnector(
            access_token="test_token",
            instagram_account_id="ig_12345",
            client_factory=client_factory,
        )
        self.assertTrue(connector.is_configured)

        results, count, errors = await connector.discover(["trailrunning"])
        self.assertEqual(len(results), 1)
        self.assertEqual(count, 2)
        self.assertEqual(errors, [])
        self.assertEqual(results[0]["metrics"]["likes"], 1420)
        self.assertEqual(results[0]["metrics"]["comments"], 88)
        self.assertEqual(results[0]["url"], "https://instagram.com/p/abc12345")

    async def test_youtube_connector_handles_quota_exceeded(self):
        def handler(request: httpx.Request) -> httpx.Response:
            return httpx.Response(
                403,
                text='{"error": {"errors": [{"reason": "quotaExceeded"}]}}',
            )

        transport = httpx.MockTransport(handler)
        client_factory = lambda **kw: httpx.AsyncClient(transport=transport, **kw)

        connector = YouTubeConnector(api_key="valid_key", client_factory=client_factory)
        results, count, errors = await connector.discover(["trail running"])
        self.assertEqual(results, [])
        self.assertEqual(count, 0)
        self.assertTrue(any("quota exceeded" in e.lower() for e in errors))

    async def test_reddit_connector_handles_rate_limiting(self):
        def handler(request: httpx.Request) -> httpx.Response:
            url_str = str(request.url)
            if "access_token" in url_str:
                return httpx.Response(200, json={"access_token": "bearer_test_123"})
            if "oauth.reddit.com/search" in url_str:
                return httpx.Response(429, headers={"x-ratelimit-reset": "30"})
            return httpx.Response(404)

        transport = httpx.MockTransport(handler)
        client_factory = lambda **kw: httpx.AsyncClient(transport=transport, **kw)

        connector = RedditConnector(
            client_id="test_id",
            client_secret="test_secret",
            client_factory=client_factory,
        )
        results, count, errors = await connector.discover(["hiking"])
        self.assertEqual(results, [])
        self.assertEqual(count, 0)
        self.assertTrue(any("rate limit exceeded" in e.lower() for e in errors))

    async def test_rss_feed_isolation_one_failing_feed_does_not_abort_others(self):
        valid_rss = (
            b'<?xml version="1.0" encoding="UTF-8"?>'
            b'<rss version="2.0"><channel><title>Good Feed</title>'
            b'<item><title>Trail Safety Alert</title><link>https://example.com/item1</link>'
            b'<description>Important safety info</description></item>'
            b'</channel></rss>'
        )

        def handler(request: httpx.Request) -> httpx.Response:
            if "bad.test" in str(request.url):
                return httpx.Response(500)
            return httpx.Response(200, content=valid_rss)

        transport = httpx.MockTransport(handler)
        client_factory = lambda **kw: httpx.AsyncClient(transport=transport, **kw)

        connector = RssConnector(
            feed_urls=["https://bad.test/feed.xml", "https://good.test/feed.xml"],
            client_factory=client_factory,
        )
        results, successful_count, errors = await connector.discover([], days=30)
        self.assertEqual(len(results), 1)
        self.assertEqual(successful_count, 1)
        self.assertTrue(any("HTTP 500" in e for e in errors))
        self.assertEqual(results[0]["title"], "Trail Safety Alert")


class ProviderOrchestrationIntegrationTests(unittest.IsolatedAsyncioTestCase):
    async def test_orchestrator_skips_unconfigured_and_executes_operational(self):
        class FakeOperationalConnector:
            source_key = "youtube"
            source_name = "YouTube Data API"
            is_configured = True

            async def discover(self, queries: list[str], days: int, market: str | None = None):
                return [
                    {
                        "source_key": "youtube",
                        "source_name": "YouTube Channel",
                        "title": "Essential Hiking Equipment Guide",
                        "url": "https://youtube.com/watch?v=hiking1",
                        "published_at": datetime.now(timezone.utc),
                        "summary": "Full review of hiking equipment.",
                        "evidence": "Video review of hiking equipment.",
                        "metrics": {"views": 12000, "likes": 550},
                    }
                ], 1, []

        orchestrator = ResearchOrchestrator(
            youtube=FakeOperationalConnector(),
            tiktok=TikTokConnector(),
            x=XConnector(),
        )

        payload = {
            "brand": {
                "id": 1,
                "name": "Trail Co",
                "niche": "hiking",
                "audience": "hikers",
                "region": "US",
                "language": "en",
                "settings": {"brand_keywords": ["hiking"]},
            },
            "filters": {
                "sources": ["youtube", "tiktok", "x"],
                "days": 30,
            },
        }

        result = await orchestrator.research(payload)
        self.assertIn("topics", result)
        self.assertIn("sources", result)
        self.assertEqual(result["result_state"], "success")

        source_statuses = {s["source_key"]: s for s in result["sources"]}
        self.assertEqual(source_statuses["youtube"]["status"], "success")
        self.assertEqual(source_statuses["tiktok"]["status"], "configuration_required")
        self.assertEqual(source_statuses["x"]["status"], "configuration_required")

        # YouTube topic is preserved and clustered
        self.assertGreaterEqual(len(result["topics"]), 1)
        topic = result["topics"][0]
        self.assertEqual(topic["title"], "Essential Hiking Equipment Guide")
        self.assertIn("youtube", topic["participating_platforms"])


if __name__ == "__main__":
    unittest.main()
