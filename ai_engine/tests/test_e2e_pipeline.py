from __future__ import annotations

import os
import sys
import unittest
from datetime import datetime, timezone

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from ai_engine.clustering import TrendClusteringEngine
from ai_engine.context import BrandContext
from ai_engine.normalization import clean_url, extract_content_tokens, normalize_text
from ai_engine.recommendation_engine import (
    BrandRelevanceEngine,
    ContentRecommendationGenerator,
    TrendRecommendationEngine,
)
from ai_engine.research import ResearchOrchestrator
from ai_engine.source_registry import SourceRegistry
from ai_engine.trend_contract import CanonicalTrend, SourceEvidence, SourceMetrics


class TestEndToEndPipeline(unittest.TestCase):
    """End-to-End verification of the complete pipeline across 3 synthetic brands."""

    def setUp(self) -> None:
        # Synthetic Brand 1: B2B DevOps / Cloud Tech
        self.brand_tech = BrandContext(
            name="DevPulse AI",
            industry="Software & Technology",
            niche="DevOps & Cloud Automation",
            category="technology",
            description="AI platform for automated cloud deployments and developer observability.",
            audience="DevOps engineers, SREs, and engineering directors",
            audience_interests=("cloud computing", "ci/cd", "kubernetes", "automation"),
            region="United States",
            language="en",
            keywords=("devops", "cloud", "automation", "kubernetes", "ai"),
            objectives=("lead generation", "thought leadership"),
            content_categories=("educational", "short_video"),
            content_preferences=("actionable tips", "framework breakdowns"),
            positive_feedback=("Kubernetes multi-cluster management",),
            negative_feedback=("Cryptocurrency speculative trading",),
            tone="authoritative",
        )

        # Synthetic Brand 2: Outdoor Gear / Mountaineering
        self.brand_outdoor = BrandContext(
            name="Summit Peak Gear",
            industry="Outdoor Recreation",
            niche="Ultralight Backpacking & Mountaineering",
            category="lifestyle",
            description="High-performance ultralight backpacking gear and apparel for alpine climbers.",
            audience="Thru-hikers, mountaineers, and wilderness adventurers",
            audience_interests=("hiking", "camping", "backpacking", "wilderness survival"),
            region="United States",
            language="en",
            keywords=("ultralight", "backpacking", "hiking", "mountaineering", "camping"),
            objectives=("community engagement", "brand awareness"),
            content_categories=("inspirational", "carousel"),
            content_preferences=("trail guides", "gear breakdowns"),
            positive_feedback=("Pacific Crest Trail ultralight shelter review",),
            negative_feedback=("Gym bodybuilding workouts",),
            tone="casual",
        )

        # Synthetic Brand 3: Gourmet Culinary / Specialty Coffee & Kitchen
        self.brand_culinary = BrandContext(
            name="Saffron & Salt Kitchen",
            industry="Food & Beverage",
            niche="Artisanal Cooking & Specialty Coffee",
            category="food",
            description="Artisanal kitchenware, specialty coffee brewing tools, and gourmet recipes.",
            audience="Home chefs, foodies, and specialty coffee enthusiasts",
            audience_interests=("cooking", "recipes", "coffee brewing", "culinary techniques"),
            region="United States",
            language="en",
            keywords=("cooking", "recipes", "coffee", "culinary", "kitchenware"),
            objectives=("ecommerce sales", "customer retention"),
            content_categories=("educational", "short_video"),
            content_preferences=("step-by-step recipes", "brewing guides"),
            positive_feedback=("Pour over extraction science",),
            negative_feedback=("Fast food drive-thru hacks",),
            tone="warm",
        )

        self.engine = TrendRecommendationEngine()

    def test_e2e_source_normalization_to_canonical_trend(self) -> None:
        """Verify raw dirty URLs and text normalize accurately into CanonicalTrend."""
        raw_url = "https://youtube.com/watch?v=k8s12345&utm_source=twitter&utm_medium=social"
        cleaned_url = clean_url(raw_url)
        self.assertEqual(cleaned_url, "https://youtube.com/watch?v=k8s12345")

        evidence = SourceEvidence(
            source_key="youtube",
            source_name="YouTube Data API",
            platform="youtube",
            title="Kubernetes 1.32 Cloud Automation Shifts",
            url=cleaned_url,
            published_at=datetime.now(timezone.utc),
            evidence="Detailed walkthrough of autonomous container scaling.",
            metrics={"views": 45000, "likes": 1200},
        )

        canonical = CanonicalTrend(
            trend_id=CanonicalTrend.generate_id("youtube", cleaned_url),
            title="Kubernetes 1.32 Cloud Automation Shifts",
            normalized_title=normalize_text("Kubernetes 1.32 Cloud Automation Shifts"),
            category="technology",
            platform="youtube",
            source_key="youtube",
            url=cleaned_url,
            keywords=extract_content_tokens("Kubernetes 1.32 Cloud Automation Shifts"),
            evidence=[evidence],
            discovered_at=datetime.now(timezone.utc),
        )

        self.assertIn("kubernetes", canonical.keywords)
        self.assertEqual(len(canonical.evidence), 1)
        self.assertEqual(canonical.evidence[0].metrics["views"], 45000)

    def test_e2e_cross_platform_clustering(self) -> None:
        """Verify items from YouTube and Reddit cluster into a unified cross-platform trend."""
        clustering_engine = TrendClusteringEngine()

        articles = [
            {
                "title": "Kubernetes 1.32 Cloud Automation Shifts",
                "url": "https://youtube.com/watch?v=k8s1",
                "source_key": "youtube",
                "source_name": "YouTube",
                "summary": "Autonomous container scaling on AWS and GCP.",
                "evidence": "Top video review",
                "metrics": {"views": 25000},
                "published_at": datetime.now(timezone.utc),
            },
            {
                "title": "Production experiences with Kubernetes cloud automation shifts",
                "url": "https://reddit.com/r/devops/comments/k8s_shifts",
                "source_key": "reddit",
                "source_name": "Reddit (r/devops)",
                "summary": "Community discussion on autonomous scaling in production.",
                "evidence": "150 comments discussing autoscaling",
                "metrics": {"comments": 150},
                "published_at": datetime.now(timezone.utc),
            },
        ]

        clusters = clustering_engine.cluster_articles(articles, default_category="technology")
        self.assertEqual(len(clusters), 1)
        cluster = clusters[0]
        self.assertTrue(cluster.is_cross_platform)
        self.assertIn("youtube", cluster.participating_platforms)
        self.assertIn("reddit", cluster.participating_platforms)
        self.assertGreaterEqual(cluster.cluster_confidence, 0.70)
        self.assertEqual(len(cluster.evidence_items), 2)

    def test_e2e_differentiation_across_three_brands(self) -> None:
        """Test Scenarios A, B, C, D across the 3 synthetic brands using the same 3 discovered trends."""
        # Trend 1: Kubernetes Cloud Automation (Tech topic)
        tech_topic = {
            "title": "Kubernetes AI Cloud Automation Best Practices",
            "summary": "How enterprise engineering teams automate cloud deployments with zero downtime.",
            "sources": [{"source_key": "youtube"}, {"source_key": "reddit"}],
            "opportunity_score": 92,
            "is_cross_platform": True,
            "cluster_id": "cl_k8s_ai",
            "participating_platforms": ["youtube", "reddit"],
        }

        # Trend 2: Ultralight Alpine Backpacking Gear (Outdoor topic)
        outdoor_topic = {
            "title": "Ultralight Backpacking Gear Shifts for Alpine Trails",
            "summary": "How thru-hikers and mountaineers are shaving pack weight below 9 lbs.",
            "sources": [{"source_key": "youtube"}, {"source_key": "reddit"}],
            "opportunity_score": 88,
            "is_cross_platform": True,
            "cluster_id": "cl_ultralight_gear",
            "participating_platforms": ["youtube", "reddit"],
        }

        # Trend 3: Specialty Coffee Extraction Science (Food/Culinary topic)
        coffee_topic = {
            "title": "Specialty Coffee Pour Over Extraction Science and Recipes",
            "summary": "New barista techniques for optimizing extraction yield and acidity balance.",
            "sources": [{"source_key": "rss"}, {"source_key": "youtube"}],
            "opportunity_score": 85,
            "is_cross_platform": True,
            "cluster_id": "cl_coffee_science",
            "participating_platforms": ["rss", "youtube"],
        }

        # --- Brand 1 (Tech) Evaluations ---
        rec_tech_on_tech = self.engine.evaluate_and_recommend(self.brand_tech, tech_topic)
        rec_tech_on_outdoor = self.engine.evaluate_and_recommend(self.brand_tech, outdoor_topic)
        rec_tech_on_coffee = self.engine.evaluate_and_recommend(self.brand_tech, coffee_topic)

        # Scenario A (Direct match for Tech)
        self.assertIsNotNone(rec_tech_on_tech)
        self.assertEqual(rec_tech_on_tech.relevance_classification, "direct")
        self.assertGreaterEqual(rec_tech_on_tech.score, 75)
        self.assertIn("DevPulse AI", rec_tech_on_tech.content_package["recommended_angle"])
        self.assertIn("strategic", rec_tech_on_tech.content_package["alternative_hooks"][1].lower())

        # Scenario D (Completely irrelevant for Tech -> Discarded)
        self.assertIsNone(rec_tech_on_outdoor)
        self.assertIsNone(rec_tech_on_coffee)

        # --- Brand 2 (Outdoor) Evaluations ---
        rec_outdoor_on_tech = self.engine.evaluate_and_recommend(self.brand_outdoor, tech_topic)
        rec_outdoor_on_outdoor = self.engine.evaluate_and_recommend(self.brand_outdoor, outdoor_topic)
        rec_outdoor_on_coffee = self.engine.evaluate_and_recommend(self.brand_outdoor, coffee_topic)

        # Scenario D (Irrelevant for Outdoor -> Discarded)
        self.assertIsNone(rec_outdoor_on_tech)

        # Scenario A (Direct match for Outdoor)
        self.assertIsNotNone(rec_outdoor_on_outdoor)
        self.assertEqual(rec_outdoor_on_outdoor.relevance_classification, "direct")
        self.assertGreaterEqual(rec_outdoor_on_outdoor.score, 75)
        self.assertIn("Summit Peak Gear", rec_outdoor_on_outdoor.content_package["recommended_angle"])
        # Casual tone hook
        self.assertTrue(any("stop scrolling" in h.lower() or "missed" in h.lower()
                            for h in rec_outdoor_on_outdoor.content_package["alternative_hooks"]))

        # Scenario D (Coffee is irrelevant for outdoor apparel -> Discarded)
        self.assertIsNone(rec_outdoor_on_coffee)

        # --- Brand 3 (Culinary) Evaluations ---
        rec_culinary_on_tech = self.engine.evaluate_and_recommend(self.brand_culinary, tech_topic)
        rec_culinary_on_outdoor = self.engine.evaluate_and_recommend(self.brand_culinary, outdoor_topic)
        rec_culinary_on_coffee = self.engine.evaluate_and_recommend(self.brand_culinary, coffee_topic)

        self.assertIsNone(rec_culinary_on_tech)
        self.assertIsNone(rec_culinary_on_outdoor)

        # Scenario A (Direct match for Culinary)
        self.assertIsNotNone(rec_culinary_on_coffee)
        self.assertEqual(rec_culinary_on_coffee.relevance_classification, "direct")
        self.assertGreaterEqual(rec_culinary_on_coffee.score, 75)
        self.assertIn("Saffron & Salt Kitchen", rec_culinary_on_coffee.content_package["recommended_angle"])

    def test_e2e_scenario_e_missing_brand_profile_information(self) -> None:
        """Scenario E: Minimal brand profile with empty settings still functions deterministically."""
        sparse_brand = BrandContext(
            name="MinimalBrand",
            industry=None,
            niche=None,
            category=None,
            description=None,
            audience=None,
            audience_interests=(),
            region=None,
            language="en",
            keywords=(),
            objectives=(),
            content_categories=(),
            content_preferences=(),
            positive_feedback=(),
            negative_feedback=(),
        )

        topic = {
            "title": "General AI Innovation Trends",
            "summary": "Latest news on AI models.",
            "sources": [{"source_key": "rss"}],
            "opportunity_score": 50,
        }

        # Sparse brand with 0 keywords produces a low/weak/irrelevant score safely without crashing
        rec = self.engine.evaluate_and_recommend(sparse_brand, topic)
        # If score is < 25, it returns None safely; if >= 25, it returns a valid package
        if rec is not None:
            self.assertTrue(rec.content_package["draft_caption"])
            self.assertEqual(len(rec.content_package["alternative_hooks"]), 3)

    def test_e2e_scenario_f_empty_or_sparse_source_evidence(self) -> None:
        """Scenario F: Topic with minimal evidence still processes without crashing."""
        sparse_topic = {
            "title": "Kubernetes Cloud Deployment Guide",
            "summary": "",
            "sources": [],
            "opportunity_score": 40,
            "is_cross_platform": False,
        }

        rec = self.engine.evaluate_and_recommend(self.brand_tech, sparse_topic)
        self.assertIsNotNone(rec)
        self.assertTrue(rec.content_package["recommended_angle"])
        self.assertEqual(len(rec.content_package["alternative_hooks"]), 3)

    def test_e2e_scenario_g_ai_provider_unavailable_deterministic_fallback(self) -> None:
        """Scenario G: When external LLM is offline/disabled, deterministic generation produces 100% complete package."""
        generator = ContentRecommendationGenerator(angle_generator=None)
        package = generator.generate(
            context=self.brand_tech,
            title="Kubernetes Container Scalability",
            summary="How teams scale containers.",
            classification="direct",
            sources=[{"source_key": "rss"}],
        )

        self.assertTrue(package.recommended_angle)
        self.assertEqual(len(package.alternative_hooks), 3)
        self.assertTrue(package.draft_caption)
        self.assertTrue(package.call_to_action)
        self.assertTrue(5 <= len(package.hashtags) <= 8)
        self.assertIn("tiktok", package.platform_concepts)
        self.assertIn("instagram", package.platform_concepts)
        self.assertIn("youtube", package.platform_concepts)
        self.assertIn("facebook", package.platform_concepts)
        self.assertIn("x", package.platform_concepts)
        self.assertIn("reddit", package.platform_concepts)


if __name__ == "__main__":
    unittest.main()
