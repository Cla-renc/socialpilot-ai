from __future__ import annotations

import os
import sys
import unittest

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from ai_engine.context import BrandContext
from ai_engine.recommendation_engine import (
    BrandRelevanceEngine,
    ContentRecommendationGenerator,
    TrendRecommendationEngine,
)


class TestRecommendationEngine(unittest.TestCase):
    def setUp(self) -> None:
        self.tech_brand = BrandContext(
            name="DevPulse AI",
            industry="Software & Technology",
            niche="DevOps & Cloud Automation",
            category="technology",
            description="AI platform for automated cloud deployments and developer observability.",
            audience="DevOps engineers and engineering leaders",
            audience_interests=("cloud computing", "ci/cd", "automation", "kubernetes"),
            region="United States",
            language="en",
            keywords=("devops", "cloud", "automation", "ai", "kubernetes"),
            objectives=("lead generation", "thought leadership"),
            content_categories=("educational", "short_video"),
            content_preferences=("actionable tips", "framework breakdowns"),
            positive_feedback=("Docker multi-stage builds optimization",),
            negative_feedback=("Cryptocurrency meme coins",),
            tone="authoritative",
        )

        self.outdoor_brand = BrandContext(
            name="Alpine Trail Gear",
            industry="Outdoor Recreation",
            niche="Ultralight Backpacking",
            category="lifestyle",
            description="Premium ultralight gear for long-distance thru-hikers and mountaineers.",
            audience="Hikers, campers, and outdoor adventurers",
            audience_interests=("hiking", "camping", "backpacking", "wilderness survival"),
            region="United States",
            language="en",
            keywords=("ultralight", "backpacking", "hiking", "trails", "camping"),
            objectives=("community engagement", "brand awareness"),
            content_categories=("inspirational", "carousel"),
            content_preferences=("trail guides", "gear breakdowns"),
            positive_feedback=("Pacific Crest Trail gear checklist",),
            negative_feedback=("Indoor fitness routines",),
            tone="casual",
        )

        self.engine = TrendRecommendationEngine()

    def test_relevance_classification_thresholds(self) -> None:
        relevance_engine = BrandRelevanceEngine()

        # High direct match for Tech Brand
        score_direct, class_direct, exp_direct = relevance_engine.evaluate(
            context=self.tech_brand,
            title="Kubernetes Cloud Automation Best Practices",
            summary="New strategies for automating DevOps pipelines with AI in cloud environments.",
            sources=[{"source_key": "news_api"}, {"source_key": "reddit"}],
            opportunity_score=85,
            is_cross_platform=True,
        )
        self.assertGreaterEqual(score_direct, 75)
        self.assertEqual(class_direct, "direct")
        self.assertIn("Directly impacts", exp_direct["why_it_matters"])
        self.assertIn("audience_interest", exp_direct)
        self.assertIn("timeliness_and_evidence", exp_direct)

        # Adjacent match (partial keywords and audience overlap)
        score_adj, class_adj, exp_adj = relevance_engine.evaluate(
            context=self.tech_brand,
            title="AI Productivity Tools Transforming Engineering Teams",
            summary="How modern engineering teams are adopting workflow improvements.",
            sources=[{"source_key": "rss"}, {"source_key": "reddit"}],
            opportunity_score=60,
            is_cross_platform=False,
        )
        self.assertTrue(45 <= score_adj < 75)
        self.assertEqual(class_adj, "adjacent")
        self.assertIn("Adjacent trend", exp_adj["why_it_matters"])

        # Irrelevant match (< 25)
        score_irr, class_irr, exp_irr = relevance_engine.evaluate(
            context=self.tech_brand,
            title="Celebrity Red Carpet Fashion Trends 2026",
            summary="The latest designer dresses and red carpet styles from award season.",
            sources=[{"source_key": "rss"}],
            opportunity_score=40,
            is_cross_platform=False,
        )
        self.assertLess(score_irr, 25)
        self.assertEqual(class_irr, "irrelevant")

    def test_distinct_recommendations_across_different_brands(self) -> None:
        """The exact same trending topics must yield starkly different recommendations for Tech vs Outdoor brand."""
        cloud_topic = {
            "title": "Cloud Cost Optimization with Kubernetes AI",
            "summary": "Engineering teams are cutting cloud spend by 40% using automated container scheduling.",
            "sources": [{"source_key": "news_api"}, {"source_key": "youtube"}],
            "opportunity_score": 90,
            "is_cross_platform": True,
            "cluster_id": "cluster_cloud_01",
            "participating_platforms": ["news_api", "youtube"],
        }

        hiking_topic = {
            "title": "Ultralight Backpacking Gear Shifts for 2026",
            "summary": "How thru-hikers are trimming pack weight under 10 lbs with new titanium and dyneema materials.",
            "sources": [{"source_key": "reddit"}, {"source_key": "youtube"}],
            "opportunity_score": 88,
            "is_cross_platform": True,
            "cluster_id": "cluster_hike_01",
            "participating_platforms": ["reddit", "youtube"],
        }

        # 1. Tech Brand evaluation
        tech_cloud_rec = self.engine.evaluate_and_recommend(self.tech_brand, cloud_topic)
        tech_hike_rec = self.engine.evaluate_and_recommend(self.tech_brand, hiking_topic)

        self.assertIsNotNone(tech_cloud_rec)
        self.assertEqual(tech_cloud_rec.relevance_classification, "direct")
        self.assertIn("DevPulse AI", tech_cloud_rec.content_package["recommended_angle"])
        self.assertIn("DevOps & Cloud Automation", tech_cloud_rec.content_package["recommended_angle"])
        # Hiking topic is irrelevant for tech brand
        self.assertIsNone(tech_hike_rec)

        # 2. Outdoor Brand evaluation
        outdoor_cloud_rec = self.engine.evaluate_and_recommend(self.outdoor_brand, cloud_topic)
        outdoor_hike_rec = self.engine.evaluate_and_recommend(self.outdoor_brand, hiking_topic)

        # Cloud topic is irrelevant for outdoor brand
        self.assertIsNone(outdoor_cloud_rec)
        self.assertIsNotNone(outdoor_hike_rec)
        self.assertEqual(outdoor_hike_rec.relevance_classification, "direct")
        self.assertIn("Alpine Trail Gear", outdoor_hike_rec.content_package["recommended_angle"])
        self.assertIn("Ultralight Backpacking", outdoor_hike_rec.content_package["recommended_angle"])

        # Compare hooks: tech tone (authoritative) vs outdoor tone (casual)
        self.assertEqual(len(tech_cloud_rec.content_package["alternative_hooks"]), 3)
        self.assertEqual(len(outdoor_hike_rec.content_package["alternative_hooks"]), 3)
        # Tech brand hooks use authoritative language
        self.assertTrue(
            any("strategic" in h.lower() or "leaders" in h.lower() or "beyond the hype" in h.lower()
                for h in tech_cloud_rec.content_package["alternative_hooks"])
        )
        # Outdoor brand hooks use casual language
        self.assertTrue(
            any("stop scrolling" in h.lower() or "missed" in h.lower() or "settle this" in h.lower()
                for h in outdoor_hike_rec.content_package["alternative_hooks"])
        )

    def test_content_package_platform_adaptations(self) -> None:
        generator = ContentRecommendationGenerator()
        package = generator.generate(
            context=self.tech_brand,
            title="Microservices Observability Trends",
            summary="New tools for tracing and distributed metrics in production architectures.",
            classification="direct",
            sources=[{"source_key": "reddit"}],
        )

        # Structure checks
        self.assertEqual(len(package.alternative_hooks), 3)
        self.assertTrue(5 <= len(package.hashtags) <= 8)
        self.assertTrue(package.draft_caption)
        self.assertTrue(package.call_to_action)

        # Multi-platform concepts
        concepts = package.platform_concepts
        self.assertIn("tiktok", concepts)
        self.assertIn("instagram", concepts)
        self.assertIn("youtube", concepts)
        self.assertIn("facebook", concepts)
        self.assertIn("x", concepts)
        self.assertIn("reddit", concepts)

        # Verify platform-specific properties
        self.assertIn("visual_concept", concepts["tiktok"])
        self.assertIn("slide_progression", concepts["instagram"])
        self.assertIn("thumbnail_text", concepts["youtube"])
        self.assertIn("starter_question", concepts["facebook"])
        self.assertIn("tweet_1", concepts["x"])
        self.assertIn("suggested_subreddits", concepts["reddit"])

    def test_negative_feedback_suppression(self) -> None:
        relevance_engine = BrandRelevanceEngine()
        # Topic containing negative feedback terms
        score, classification, _ = relevance_engine.evaluate(
            context=self.tech_brand,
            title="Cryptocurrency Meme Coins Staking Guide",
            summary="New ways to stake meme coins for speculative yields.",
            sources=[{"source_key": "rss"}],
            opportunity_score=70,
        )
        # Should be penalized into irrelevance (< 25)
        self.assertLess(score, 25)
        self.assertEqual(classification, "irrelevant")


if __name__ == "__main__":
    unittest.main()
