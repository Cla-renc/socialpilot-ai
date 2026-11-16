from __future__ import annotations

from datetime import datetime, timedelta, timezone
import json
import unittest
from unittest.mock import patch

import httpx
from fastapi.testclient import TestClient

from ai_engine.context import BrandContext, generate_queries
from ai_engine.duplicate_detection import duplicate_index, normalize_title
from ai_engine.main import app
from ai_engine.momentum import calculate_momentum
from ai_engine.research import ResearchOrchestrator
from ai_engine.scoring import analyze_relevance, rank_opportunity
from ai_engine.sources.google_trends import GoogleTrendsConnector
from ai_engine.sources.news_api import NewsApiConnector
from ai_engine.sources.reddit import RedditConnector
from ai_engine.sources.rss import parse_feed, validate_feed_url
from ai_engine.sources.youtube import YouTubeConnector


class FakeRss:
    source_key = "rss"
    source_name = "Configured RSS feeds"
    feed_urls = ["https://example.test/feed.xml"]

    def __init__(self, items: list[dict], successful: int = 1, errors: list[str] | None = None):
        self.items = items
        self.successful = successful
        self.errors = errors or []

    async def discover(self, queries: list[str], days: int, market: str | None = None):
        return self.items, self.successful, self.errors


class FakeGenericConnector:
    def __init__(self, source_key: str, name: str, items: list[dict], successful: int = 1, errors: list[str] | None = None, configured: bool = True):
        self.source_key = source_key
        self.source_name = name
        self.items = items
        self.successful = successful
        self.errors = errors or []
        self.is_configured = configured

    async def discover(self, queries: list[str], days: int, market: str | None = None):
        return self.items, self.successful, self.errors


class TrendResearchTests(unittest.IsolatedAsyncioTestCase):
    def setUp(self):
        self.brand = {
            "id": 4,
            "industry": "outdoor equipment",
            "niche": "hiking",
            "audience": "weekend hikers",
            "audience_interests": ["trail safety"],
            "region": "Kenya",
            "language": "en",
            "settings": {
                "brand_keywords": ["trail safety", "hiking"],
                "marketing_objectives": ["brand_awareness"],
                "content_preferences": ["educational"],
            },
        }

    def test_brand_context_and_queries_use_saved_brand_terms(self):
        context = BrandContext.from_payload(self.brand)
        self.assertIn("hiking", context.research_terms())
        self.assertIn("hiking Kenya", generate_queries(context))

    def test_relevance_is_deterministic_and_explained(self):
        context = BrandContext.from_payload(self.brand)
        score, explanation = analyze_relevance(
            context,
            "Hiking trail safety guide",
            "Practical advice for weekend hikers.",
            datetime.now(timezone.utc),
            "Kenya",
        )
        self.assertGreaterEqual(score, 50)
        self.assertIn("brand keywords", explanation)

    def test_user_feedback_changes_relevance_for_similar_topics(self):
        baseline_context = BrandContext.from_payload(self.brand)
        feedback_context = BrandContext.from_payload({
            **self.brand,
            "trend_feedback": [{
                "feedback": "relevant",
                "title": "Hiking trail safety guide",
            }],
        })
        baseline, _ = analyze_relevance(
            baseline_context,
            "Hiking trail safety advice",
            "",
            None,
            None,
        )
        informed, explanation = analyze_relevance(
            feedback_context,
            "Hiking trail safety advice",
            "",
            None,
            None,
        )
        self.assertGreater(informed, baseline)
        self.assertIn("previously marked relevant", explanation)

    def test_opportunity_score_uses_documented_factors(self):
        recent = datetime.now(timezone.utc) - timedelta(hours=2)
        self.assertGreater(
            rank_opportunity(80, recent, "Kenya", "Kenya"),
            rank_opportunity(80, None, "Kenya", None),
        )

    def test_duplicate_detection_normalizes_case_and_near_duplicates(self):
        existing = {normalize_title("New hiking safety guide"): 1}
        self.assertIsNotNone(duplicate_index("New Hiking Safety Guide!", existing))
        self.assertEqual(normalize_title("A  Safe Trail"), "a safe trail")

    def test_rss_parser_keeps_source_dates_and_rejects_non_public_links(self):
        body = b"""<?xml version="1.0"?>
        <rss version="2.0"><channel><title>Trail News</title><item>
        <title>Hiking safety update</title><link>https://news.example/story</link>
        <description>Practical trail safety details.</description>
        <pubDate>Thu, 12 Nov 2026 10:00:00 GMT</pubDate>
        </item></channel></rss>"""
        parsed = parse_feed(body, "https://news.example/feed.xml", "RSS")
        self.assertEqual(parsed[0]["source_name"], "Trail News")
        self.assertEqual(parsed[0]["published_at"].year, 2026)
        self.assertTrue(validate_feed_url("https://news.example/feed.xml"))
        self.assertFalse(validate_feed_url("http://127.0.0.1/feed.xml"))

    async def test_source_failure_is_reported_without_claiming_success(self):
        orchestrator = ResearchOrchestrator(
            rss=FakeRss([], successful=0, errors=["unavailable"]),
        )
        result = await orchestrator.research({
            "brand": self.brand,
            "filters": {"sources": ["rss"], "days": 7},
        })
        self.assertEqual(result["topics"], [])
        self.assertEqual(result["sources"][0]["status"], "error")

    async def test_partial_source_failure_preserves_successful_evidence(self):
        article = {
            "source_key": "rss",
            "source_name": "Example News",
            "title": "Hiking trail safety guide",
            "url": "https://example.test/story",
            "published_at": datetime.now(timezone.utc),
            "summary": "Trail safety for hikers.",
            "evidence": "Trail safety for hikers.",
            "metrics": {},
        }
        unrelated = {
            **article,
            "title": "New laptop buying guide",
            "url": "https://example.test/laptops",
            "summary": "A consumer electronics guide.",
            "evidence": "A consumer electronics guide.",
        }
        orchestrator = ResearchOrchestrator(
            rss=FakeRss([article, unrelated], successful=1, errors=["one other feed failed"]),
        )
        result = await orchestrator.research({
            "brand": self.brand,
            "filters": {"sources": ["rss"], "days": 30},
        })
        self.assertEqual(result["sources"][0]["status"], "partial")
        self.assertEqual(len(result["topics"]), 1)
        self.assertEqual(result["topics"][0]["title"], article["title"])
        self.assertIsNone(result["topics"][0]["momentum"])
        self.assertEqual(len(result["topics"][0]["sources"]), 1)
        self.assertEqual(result["topics"][0]["content_angle_status"], "disabled")

    async def test_optional_ollama_failure_is_reported_without_losing_evidence(self):
        article = {
            "source_key": "rss",
            "source_name": "Example News",
            "title": "Hiking trail safety guide",
            "url": "https://example.test/story",
            "published_at": datetime.now(timezone.utc),
            "summary": "Trail safety for hikers.",
            "evidence": "Trail safety for hikers.",
            "metrics": {},
        }

        class FailingAngleGenerator:
            enabled = True
            configured = True

            async def suggest(self, *args):
                raise httpx.ConnectError("private service details")

        result = await ResearchOrchestrator(
            rss=FakeRss([article]),
            angle_generator=FailingAngleGenerator(),
        ).research({
            "brand": self.brand,
            "filters": {"sources": ["rss"], "days": 30},
        })

        topic = result["topics"][0]
        self.assertEqual(topic["content_angle_status"], "failed")
        self.assertIn("still available", topic["content_angle_error"])
        self.assertNotIn("private service details", topic["content_angle_error"])
        self.assertEqual(len(topic["sources"]), 1)

    async def test_no_live_feed_configuration_returns_configuration_required(self):
        orchestrator = ResearchOrchestrator(rss=FakeRss([], successful=0))
        orchestrator.rss.feed_urls = []
        result = await orchestrator.research({
            "brand": self.brand,
            "filters": {"sources": ["rss"], "days": 30},
        })
        self.assertEqual(result["sources"][0]["status"], "configuration_required")
        self.assertEqual(result["topics"], [])

    # === NEWS API TESTS ===
    async def test_news_api_connector_unconfigured(self):
        connector = NewsApiConnector(api_key="")
        self.assertFalse(connector.is_configured)
        items, count, errors = await connector.discover(["hiking safety"])
        self.assertEqual(items, [])
        self.assertEqual(count, 0)
        self.assertIn("NEWSAPI_API_KEY", errors[0])

    async def test_news_api_connector_success_with_mock(self):
        mock_response = {
            "status": "ok",
            "totalResults": 1,
            "articles": [{
                "source": {"name": "BBC News"},
                "title": "Top Hiking Safety Practices for National Parks",
                "description": "Essential guidelines for hikers on rugged trails.",
                "url": "https://www.bbc.com/news/hiking-safety",
                "publishedAt": "2026-11-12T12:00:00Z",
                "content": "Full article text...",
            }],
        }

        def mock_client_factory(**kwargs):
            transport = httpx.MockTransport(
                lambda request: httpx.Response(200, json=mock_response)
            )
            return httpx.AsyncClient(transport=transport)

        connector = NewsApiConnector(api_key="test-key", client_factory=mock_client_factory)
        self.assertTrue(connector.is_configured)
        items, count, errors = await connector.discover(["hiking safety"], days=7, market="Kenya")
        self.assertEqual(count, 1)
        self.assertEqual(len(items), 1)
        self.assertEqual(items[0]["source_key"], "news_api")
        self.assertEqual(items[0]["source_name"], "News API (BBC News)")
        self.assertEqual(items[0]["title"], "Top Hiking Safety Practices for National Parks")
        self.assertEqual(items[0]["published_at"].year, 2026)

    async def test_news_api_rate_limit_and_unauthorized(self):
        # 401 Unauthorized
        connector_401 = NewsApiConnector(
            api_key="bad-key",
            client_factory=lambda **kw: httpx.AsyncClient(
                transport=httpx.MockTransport(lambda r: httpx.Response(401, text="Unauthorized"))
            ),
        )
        _, _, errors_401 = await connector_401.discover(["hiking"])
        self.assertIn("Invalid or unauthorized", errors_401[0])

        # 429 Rate Limit
        connector_429 = NewsApiConnector(
            api_key="limited-key",
            client_factory=lambda **kw: httpx.AsyncClient(
                transport=httpx.MockTransport(lambda r: httpx.Response(429, text="Rate limit"))
            ),
        )
        _, _, errors_429 = await connector_429.discover(["hiking"])
        self.assertIn("rate limit exceeded", errors_429[0])

    # === REDDIT API TESTS ===
    async def test_reddit_connector_unconfigured(self):
        connector = RedditConnector(client_id="", client_secret="")
        self.assertFalse(connector.is_configured)
        items, count, errors = await connector.discover(["hiking safety"])
        self.assertEqual(items, [])
        self.assertEqual(count, 0)
        self.assertIn("REDDIT_CLIENT_ID", errors[0])

    async def test_reddit_connector_success_with_mock(self):
        token_resp = {"access_token": "mock-token-xyz", "token_type": "bearer", "expires_in": 3600}
        search_resp = {
            "data": {
                "children": [{
                    "data": {
                        "title": "Community tips on mountain hiking safety gear",
                        "permalink": "/r/hiking/comments/12345/tips_on_mountain_hiking/",
                        "subreddit": "hiking",
                        "subreddit_name_prefixed": "r/hiking",
                        "created_utc": 1794500000,
                        "selftext": "Always pack proper water purification and insulation.",
                        "score": 420,
                        "num_comments": 85,
                    }
                }]
            }
        }

        def mock_handler(request: httpx.Request):
            if "access_token" in str(request.url):
                return httpx.Response(200, json=token_resp)
            return httpx.Response(200, json=search_resp)

        connector = RedditConnector(
            client_id="test-client-id",
            client_secret="test-client-secret",
            client_factory=lambda **kw: httpx.AsyncClient(transport=httpx.MockTransport(mock_handler)),
        )
        self.assertTrue(connector.is_configured)
        items, count, errors = await connector.discover(["hiking gear"], days=30)
        self.assertEqual(count, 1)
        self.assertEqual(len(items), 1)
        self.assertEqual(items[0]["source_key"], "reddit")
        self.assertEqual(items[0]["source_name"], "Reddit API (r/hiking)")
        self.assertEqual(items[0]["metrics"]["likes"], 420)
        self.assertEqual(items[0]["metrics"]["comments"], 85)
        self.assertIn("reddit.com", items[0]["url"])

    async def test_reddit_connector_rate_limit(self):
        def mock_handler(request: httpx.Request):
            if "access_token" in str(request.url):
                return httpx.Response(200, json={"access_token": "ok"})
            return httpx.Response(429, headers={"x-ratelimit-reset": "60"}, text="Too Many Requests")

        connector = RedditConnector(
            client_id="cid", client_secret="csec",
            client_factory=lambda **kw: httpx.AsyncClient(transport=httpx.MockTransport(mock_handler)),
        )
        _, _, errors = await connector.discover(["hiking"])
        self.assertIn("rate limit exceeded", errors[0])

    # === YOUTUBE DATA API TESTS ===
    async def test_youtube_connector_unconfigured(self):
        connector = YouTubeConnector(api_key="")
        self.assertFalse(connector.is_configured)
        items, count, errors = await connector.discover(["hiking trails"])
        self.assertEqual(items, [])
        self.assertEqual(count, 0)
        self.assertIn("YOUTUBE_API_KEY", errors[0])

    async def test_youtube_connector_success_with_mock(self):
        search_resp = {
            "items": [{
                "id": {"videoId": "vid123"},
                "snippet": {
                    "title": "Complete Wilderness Hiking Safety Guide 2026",
                    "description": "Master essential trail skills and navigation.",
                    "channelTitle": "Outdoor Adventures",
                    "publishedAt": "2026-11-10T14:30:00Z",
                }
            }]
        }
        videos_resp = {
            "items": [{
                "id": "vid123",
                "statistics": {
                    "viewCount": "125000",
                    "likeCount": "8400",
                    "commentCount": "320",
                }
            }]
        }

        def mock_handler(request: httpx.Request):
            if "search" in str(request.url):
                return httpx.Response(200, json=search_resp)
            return httpx.Response(200, json=videos_resp)

        connector = YouTubeConnector(
            api_key="test-yt-key",
            client_factory=lambda **kw: httpx.AsyncClient(transport=httpx.MockTransport(mock_handler)),
        )
        self.assertTrue(connector.is_configured)
        items, count, errors = await connector.discover(["wilderness hiking"], days=30)
        self.assertEqual(count, 1)
        self.assertEqual(len(items), 1)
        self.assertEqual(items[0]["source_key"], "youtube")
        self.assertEqual(items[0]["source_name"], "YouTube Data API (Outdoor Adventures)")
        self.assertEqual(items[0]["metrics"]["views"], 125000)
        self.assertEqual(items[0]["metrics"]["likes"], 8400)
        self.assertEqual(items[0]["metrics"]["comments"], 320)
        self.assertIn("125,000 views", items[0]["evidence"])

    async def test_youtube_quota_exceeded(self):
        connector = YouTubeConnector(
            api_key="key",
            client_factory=lambda **kw: httpx.AsyncClient(
                transport=httpx.MockTransport(lambda r: httpx.Response(403, text='{"error": {"errors": [{"reason": "quotaExceeded"}]}}'))
            ),
        )
        _, _, errors = await connector.discover(["hiking"])
        self.assertIn("quota exceeded", errors[0])

    # === GOOGLE TRENDS TESTS ===
    async def test_google_trends_connector_rss_parsing(self):
        rss_body = b"""<?xml version="1.0" encoding="UTF-8"?>
        <rss xmlns:ht="https://trends.google.com/trending/rss" version="2.0">
        <channel><title>Daily Search Trends</title>
        <item>
            <title>Mount Kenya Hiking</title>
            <ht:approx_traffic>20,000+</ht:approx_traffic>
            <pubDate>Wed, 11 Nov 2026 08:00:00 GMT</pubDate>
            <ht:news_item>
                <ht:news_item_title>Record Numbers Register for Mount Kenya Expedition</ht:news_item_title>
                <ht:news_item_url>https://news.example/mt-kenya-expedition</ht:news_item_url>
                <ht:news_item_source>Daily Nation</ht:news_item_source>
                <ht:news_item_snippet>Trail management reports surge in mountain hiking interest.</ht:news_item_snippet>
            </ht:news_item>
        </item>
        </channel></rss>"""

        connector = GoogleTrendsConnector(
            client_factory=lambda **kw: httpx.AsyncClient(
                transport=httpx.MockTransport(lambda r: httpx.Response(200, content=rss_body))
            ),
        )
        self.assertTrue(connector.is_configured)
        items, count, errors = await connector.discover(["hiking"], days=30, market="Kenya")
        self.assertEqual(count, 1)
        self.assertEqual(len(items), 1)
        self.assertEqual(items[0]["source_key"], "google_trends")
        self.assertIn("Daily Nation", items[0]["source_name"])
        self.assertEqual(items[0]["metrics"]["interest"], 20000)
        self.assertEqual(items[0]["url"], "https://news.example/mt-kenya-expedition")

    # === MOMENTUM CLASSIFICATION TESTS ===
    def test_momentum_requires_sufficient_historical_observations(self):
        now = datetime.now(timezone.utc)
        sources = [{"source_key": "rss", "title": "Safety Guide", "published_at": now}]

        # 1. No historical observations -> None (Insufficient evidence)
        momentum, reason = calculate_momentum("Trail Safety Guide", now, sources, [])
        self.assertIsNone(momentum)
        self.assertIn("Insufficient historical evidence", reason)

        # 2. Topic with high relevance NEVER becomes growing without temporal evidence
        momentum, _ = calculate_momentum(
            "Trail Safety Guide", now, sources,
            [{"title": "Unrelated Topic", "researched_at": now - timedelta(days=5)}]
        )
        self.assertIsNone(momentum)

    def test_momentum_classifications(self):
        now = datetime.now(timezone.utc)
        sources = [
            {"source_key": "rss", "title": "Safety Guide 1", "published_at": now},
            {"source_key": "news_api", "title": "Safety Guide 2", "published_at": now - timedelta(hours=2)},
            {"source_key": "reddit", "title": "Safety Guide 3", "published_at": now - timedelta(hours=3)},
        ]

        # Growing: citations increased from 1 to 3 (> 20% increase)
        growing_past = [{
            "title": "Trail Safety Guide",
            "researched_at": now - timedelta(days=3),
            "source_count": 1,
        }]
        momentum_growing, evidence = calculate_momentum("Trail Safety Guide", now, sources, growing_past)
        self.assertEqual(momentum_growing, "growing")
        self.assertIn("increased", evidence)

        # Emerging: observed first time 24h ago, now on second observation with high relative velocity
        emerging_past = [{
            "title": "Breakthrough Trail Tech",
            "researched_at": now - timedelta(hours=24),
            "source_count": 2,
        }]
        momentum_emerging, _ = calculate_momentum("Breakthrough Trail Tech", now, sources, emerging_past)
        self.assertIn(momentum_emerging, ["emerging", "growing"])

        # Declining: citations dropped from past peak of 6 down to 1
        declining_past = [
            {"title": "Old Trail Regulation", "researched_at": now - timedelta(days=10), "source_count": 6},
            {"title": "Old Trail Regulation", "researched_at": now - timedelta(days=4), "source_count": 4},
        ]
        single_source = [{"source_key": "rss", "title": "Regulation", "published_at": now}]
        momentum_declining, _ = calculate_momentum("Old Trail Regulation", now, single_source, declining_past)
        self.assertEqual(momentum_declining, "declining")

        # Evergreen: observed across 25 days with steady citations
        evergreen_past = [
            {"title": "Essential Hiking Equipment", "researched_at": now - timedelta(days=25), "source_count": 3},
            {"title": "Essential Hiking Equipment", "researched_at": now - timedelta(days=16), "source_count": 3},
            {"title": "Essential Hiking Equipment", "researched_at": now - timedelta(days=8), "source_count": 3},
        ]
        momentum_evergreen, _ = calculate_momentum("Essential Hiking Equipment", now, sources, evergreen_past)
        self.assertEqual(momentum_evergreen, "evergreen")

    # === MULTI-SOURCE ORCHESTRATION TEST ===
    async def test_multi_source_orchestrator_execution(self):
        now = datetime.now(timezone.utc)
        news_item = {
            "source_key": "news_api",
            "source_name": "News API (Outdoor Journal)",
            "title": "Hiking trail safety guide in Kenya",
            "url": "https://example.test/news/safety",
            "published_at": now,
            "summary": "Practical trail safety tips.",
            "evidence": "Practical trail safety tips.",
            "metrics": {},
        }
        reddit_item = {
            "source_key": "reddit",
            "source_name": "Reddit API (r/hiking)",
            "title": "Hiking trail safety gear recommendations",
            "url": "https://reddit.com/r/hiking/gear",
            "published_at": now - timedelta(hours=1),
            "summary": "Community gear reviews.",
            "evidence": "Community gear reviews.",
            "metrics": {"likes": 50, "comments": 12},
        }

        fake_news = FakeGenericConnector("news_api", "News API", [news_item])
        fake_reddit = FakeGenericConnector("reddit", "Reddit API", [reddit_item])
        fake_youtube = FakeGenericConnector("youtube", "YouTube Data API", [], configured=False)

        orchestrator = ResearchOrchestrator(
            rss=FakeRss([]),
            news_api=fake_news,
            reddit=fake_reddit,
            youtube=fake_youtube,
        )

        result = await orchestrator.research({
            "brand": self.brand,
            "filters": {"sources": ["news_api", "reddit", "youtube"], "days": 30},
        })

        source_statuses = {s["source_key"]: s["status"] for s in result["sources"]}
        self.assertEqual(source_statuses["news_api"], "success")
        self.assertEqual(source_statuses["reddit"], "success")
        self.assertEqual(source_statuses["youtube"], "configuration_required")
        self.assertGreater(len(result["topics"]), 0)


class InternalApiTests(unittest.TestCase):
    def setUp(self):
        self.client = TestClient(app)
        self.payload = {
            "research_id": 1,
            "brand": {"industry": "outdoor"},
            "filters": {"days": 30},
        }

    def tearDown(self):
        if hasattr(self, 'client') and self.client:
            self.client.close()

    def test_internal_endpoint_requires_shared_laravel_token(self):
        with patch.dict("os.environ", {"TREND_ENGINE_INTERNAL_TOKEN": "engine-secret"}):
            self.assertEqual(self.client.post(
                "/internal/v1/research",
                json=self.payload,
            ).status_code, 401)
            self.assertEqual(self.client.post(
                "/internal/v1/research",
                json=self.payload,
                headers={"Authorization": "Bearer wrong"},
            ).status_code, 401)

    def test_internal_endpoint_accepts_authenticated_research_request(self):
        class FakeOrchestrator:
            async def research(self, payload):
                return {"topics": [], "sources": [], "received_id": payload["research_id"]}

        with (
            patch.dict("os.environ", {"TREND_ENGINE_INTERNAL_TOKEN": "engine-secret"}),
            patch("ai_engine.main.orchestrator", FakeOrchestrator()),
        ):
            response = self.client.post(
                "/internal/v1/research",
                json=self.payload,
                headers={"Authorization": "Bearer engine-secret"},
            )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()["received_id"], 1)


if __name__ == "__main__":
    unittest.main()
