from __future__ import annotations

from datetime import datetime, timezone
import unittest

from ai_engine.clustering import (
    DEFAULT_SIMILARITY_THRESHOLD,
    TrendClusteringEngine,
    calculate_title_similarity,
)
from ai_engine.normalization import (
    clean_url,
    extract_content_tokens,
    extract_hashtags,
    extract_tokens,
    normalize_text,
    normalize_trend_title,
)
from ai_engine.trend_contract import (
    CanonicalTrend,
    SourceEvidence,
    SourceMetrics,
    TrendCluster,
)


class NormalizationTests(unittest.TestCase):
    def test_unicode_and_whitespace_normalization(self):
        raw = "  AI Tools   Transforming   Small  Businesses—A 2026 Guide…  "
        normalized = normalize_text(raw)
        self.assertEqual(normalized, "ai tools transforming small businesses - a 2026 guide...")

    def test_smart_quotes_and_typographical_symbols(self):
        raw = "“Expert’s Guide to ‘Trail Safety’ — Winter Edition”"
        normalized = normalize_text(raw)
        self.assertEqual(normalized, '"expert\'s guide to \'trail safety\' - winter edition"')

    def test_clean_url_strips_tracking_parameters(self):
        url = "https://example.com/blog/ai-marketing?utm_source=twitter&utm_medium=social&utm_campaign=launch&ref=producthunt&fbclid=IwAR123#section-3"
        cleaned = clean_url(url)
        self.assertEqual(cleaned, "https://example.com/blog/ai-marketing")

    def test_clean_url_preserves_legitimate_queries(self):
        url = "https://trends.google.com/trends/explore?geo=US&q=hiking"
        cleaned = clean_url(url)
        self.assertEqual(cleaned, "https://trends.google.com/trends/explore?geo=US&q=hiking")

    def test_clean_url_handles_empty_and_malformed(self):
        self.assertEqual(clean_url(""), "")
        self.assertEqual(clean_url(None), "")
        self.assertEqual(clean_url("not-a-valid-url"), "not-a-valid-url")

    def test_extract_hashtags(self):
        text = "Discover the best #TrailRunning and #Hiking adventures in 2026! #trailrunning"
        tags = extract_hashtags(text)
        self.assertEqual(tags, ["trailrunning", "hiking"])

    def test_extract_content_tokens_preserves_meaningful_acronyms(self):
        text = "Top AI & SaaS Growth Strategies for B2B Founders in 2026"
        tokens = extract_content_tokens(text)
        self.assertIn("ai", tokens)
        self.assertIn("saas", tokens)
        self.assertIn("growth", tokens)
        self.assertIn("strategies", tokens)
        self.assertIn("b2b", tokens)
        self.assertNotIn("for", tokens)
        self.assertNotIn("in", tokens)

    def test_normalize_trend_title(self):
        self.assertEqual(
            normalize_trend_title("How AI Tools Are Changing Small Businesses!"),
            "how ai tools are changing small businesses",
        )
        self.assertEqual(normalize_trend_title(""), "")
        self.assertEqual(normalize_trend_title(None), "")


class CanonicalTrendContractTests(unittest.TestCase):
    def test_source_metrics_preserves_nulls_without_manufacturing_zeros(self):
        metrics = SourceMetrics(views=1500, likes=None, comments=45)
        d = metrics.to_dict()
        self.assertEqual(d["views"], 1500)
        self.assertEqual(d["comments"], 45)
        self.assertNotIn("likes", d)
        self.assertNotIn("shares", d)

    def test_canonical_trend_identity_generation_is_stable(self):
        id1 = CanonicalTrend.generate_id("youtube", "https://youtube.com/watch?v=abc")
        id2 = CanonicalTrend.generate_id("youtube", "https://youtube.com/watch?v=abc")
        id3 = CanonicalTrend.generate_id("reddit", "https://reddit.com/r/hiking/123")
        self.assertEqual(id1, id2)
        self.assertNotEqual(id1, id3)
        self.assertTrue(id1.startswith("tr_youtube_"))

    def test_trend_cluster_identity_is_deterministic(self):
        id1 = TrendCluster.generate_id("sustainable trail running gear")
        id2 = TrendCluster.generate_id("sustainable trail running gear")
        self.assertEqual(id1, id2)
        self.assertTrue(id1.startswith("cl_"))


class CrossPlatformClusteringTests(unittest.TestCase):
    def setUp(self):
        self.engine = TrendClusteringEngine(similarity_threshold=0.55)

    def test_title_similarity_similar_wordings(self):
        title_a = "AI Tools Transforming Small Businesses"
        title_b = "How AI Tools Are Changing Small Businesses"
        sim = calculate_title_similarity(title_a, title_b)
        self.assertGreaterEqual(sim, 0.55)

    def test_title_similarity_rejects_generic_single_word_overlap(self):
        title_a = "Trail Safety Guide 2026"
        title_b = "Holiday Gift Guide 2026"
        sim = calculate_title_similarity(title_a, title_b)
        self.assertLess(sim, 0.55)

    def test_cross_platform_cluster_groups_youtube_reddit_google_trends(self):
        now = datetime.now(timezone.utc)
        articles = [
            {
                "source_key": "youtube",
                "title": "Sustainable Trail Running Gear 2026 Review",
                "url": "https://youtube.com/watch?v=gear1",
                "published_at": now,
                "summary": "Full review of recycled trail gear.",
                "metrics": {"views": 50000, "likes": 3200},
            },
            {
                "source_key": "reddit",
                "title": "Sustainable Trail Running Gear Discussion",
                "url": "https://reddit.com/r/trailrunning/comments/abc",
                "published_at": now,
                "summary": "Community experiences with eco trail running gear.",
                "metrics": {"score": 420, "comments": 89},
            },
            {
                "source_key": "google_trends",
                "title": "Sustainable Trail Running Gear",
                "url": "https://trends.google.com/trending/rss?q=gear",
                "published_at": now,
                "summary": "Breakout search interest.",
                "metrics": {"interest": 20000},
            },
        ]
        clusters = self.engine.cluster_articles(articles)
        self.assertEqual(len(clusters), 1)
        cluster = clusters[0]
        self.assertTrue(cluster.is_cross_platform)
        self.assertEqual(len(cluster.participating_platforms), 3)
        self.assertIn("youtube", cluster.participating_platforms)
        self.assertIn("reddit", cluster.participating_platforms)
        self.assertIn("google_trends", cluster.participating_platforms)
        self.assertEqual(cluster.distinct_source_count, 3)
        self.assertEqual(cluster.evidence_count, 3)
        self.assertGreaterEqual(cluster.cross_platform_weight, 1.25)
        self.assertGreaterEqual(cluster.cluster_confidence, 0.90)

    def test_unrelated_trends_remain_separate(self):
        articles = [
            {
                "source_key": "rss",
                "title": "Breakthrough in Fusion Energy Technology",
                "url": "https://bbc.com/news/fusion-1",
            },
            {
                "source_key": "youtube",
                "title": "Top 10 Ultra Trail Running Shoes",
                "url": "https://youtube.com/watch?v=shoes10",
            },
            {
                "source_key": "reddit",
                "title": "Best Coffee Roasting Techniques for Beginners",
                "url": "https://reddit.com/r/coffee/comments/xyz",
            },
        ]
        clusters = self.engine.cluster_articles(articles)
        self.assertEqual(len(clusters), 3)
        for cluster in clusters:
            self.assertFalse(cluster.is_cross_platform)
            self.assertEqual(len(cluster.participating_platforms), 1)

    def test_idempotent_deduplication_of_exact_same_url(self):
        articles = [
            {
                "source_key": "youtube",
                "title": "Trail Running Essentials",
                "url": "https://youtube.com/watch?v=same1&utm_source=twitter",
            },
            {
                "source_key": "youtube",
                "title": "Trail Running Essentials",
                "url": "https://youtube.com/watch?v=same1",
            },
        ]
        clusters = self.engine.cluster_articles(articles)
        self.assertEqual(len(clusters), 1)
        # The duplicate URL should be deduplicated
        self.assertEqual(clusters[0].evidence_count, 1)

    def test_empty_and_malformed_inputs(self):
        self.assertEqual(self.engine.cluster_articles([]), [])
        articles = [
            {"source_key": "rss", "title": "", "url": "https://example.com/empty"},
            {"source_key": "rss", "title": "   ", "url": "https://example.com/spaces"},
        ]
        self.assertEqual(self.engine.cluster_articles(articles), [])

    def test_bounded_performance_with_many_items(self):
        # Verify sub-second performance on 150 items
        now = datetime.now(timezone.utc)
        items = []
        for i in range(150):
            topic_idx = i % 10
            items.append({
                "source_key": "reddit" if i % 2 == 0 else "rss",
                "title": f"Topic Number {topic_idx} Comprehensive Guide to Digital Marketing Strategy {i}",
                "url": f"https://example.com/article-{i}",
                "published_at": now,
            })
        start = datetime.now()
        clusters = self.engine.cluster_articles(items)
        duration_ms = (datetime.now() - start).total_seconds() * 1000
        # Must execute within 200ms on Render Free CPU
        self.assertLess(duration_ms, 250)
        self.assertGreater(len(clusters), 0)


if __name__ == "__main__":
    unittest.main()
