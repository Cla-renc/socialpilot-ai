"""
Phase 8 AI Scheduling and Autopilot Agent Unit Tests
"""
from __future__ import annotations

import unittest
from datetime import datetime, timedelta, timezone as dt_timezone
from unittest.mock import patch

from ai_engine.best_time_to_post import BestTimeToPostService
from ai_engine.scheduling_intelligence import SchedulingIntelligenceService
from ai_engine.scheduling_conflict_detection import SchedulingConflictDetector
from ai_engine.autopilot_strategy import AutopilotStrategyService
from ai_engine.content_distribution import ContentDistributionService
from ai_engine.publishing_schedule import PublishingScheduleBuilder
from ai_engine.scheduling_recommendations import SchedulingRecommendationCoordinator


class SchedulingAIEngineTests(unittest.TestCase):
    def setUp(self):
        self.discipline_brand = {
            "id": 1,
            "name": "Apex Stoic Discipline",
            "industry": "Fitness & Personal Development",
            "category": "mindset and daily habits",
            "niche": "High performance discipline",
            "audience": "Founders, executives, and athletes",
            "timezone": "Africa/Nairobi",
            "connected_platforms": ["linkedin", "youtube", "instagram"],
        }
        self.retail_brand = {
            "id": 2,
            "name": "Nova Apparel",
            "industry": "Retail & E-commerce",
            "category": "fashion and streetwear",
            "niche": "Urban sustainable clothing",
            "audience": "Gen-Z and millennials",
            "timezone": "America/New_York",
            "connected_platforms": ["instagram", "tiktok"],
        }

    def test_best_time_to_post_generates_supported_platforms(self):
        recs = BestTimeToPostService.get_recommendations(self.discipline_brand)
        self.assertGreater(len(recs), 0)
        platforms_found = {r["platform"] for r in recs}
        for plat in ["facebook", "instagram", "tiktok", "youtube", "linkedin"]:
            self.assertIn(plat, platforms_found)

    def test_best_time_to_post_filters_by_platform(self):
        recs = BestTimeToPostService.get_recommendations(self.discipline_brand, platform="linkedin")
        self.assertGreater(len(recs), 0)
        for r in recs:
            self.assertEqual(r["platform"], "linkedin")
            self.assertEqual(r["evidence_type"], "brand_profile_aligned")
            self.assertTrue(r["is_preliminary"])
            self.assertIn("Africa/Nairobi", r["timezone"])

    def test_brand_archetype_distinct_recommendations(self):
        # Discipline brand should have earlier slots
        discipline_recs = BestTimeToPostService.get_recommendations(self.discipline_brand, platform="instagram")
        retail_recs = BestTimeToPostService.get_recommendations(self.retail_brand, platform="instagram")

        discipline_times = [r["suggested_time"] for r in discipline_recs]
        retail_times = [r["suggested_time"] for r in retail_recs]

        # Ensure different heuristics are applied
        self.assertNotEqual(discipline_times, retail_times)
        self.assertIn("Audience values morning routine", discipline_recs[0]["reason"])
        self.assertIn("shopping intent", retail_recs[0]["reason"])

    def test_historical_performance_distinguishes_evidence_type(self):
        history = [
            {"platform": "linkedin", "day_of_week": "tuesday", "time": "09:30", "engagement_score": 85.0},
            {"platform": "linkedin", "day_of_week": "thursday", "time": "14:00", "engagement_score": 92.0},
            {"platform": "linkedin", "day_of_week": "wednesday", "time": "11:00", "engagement_score": 78.0},
            {"platform": "linkedin", "day_of_week": "friday", "time": "16:00", "engagement_score": 60.0},
            {"platform": "linkedin", "day_of_week": "monday", "time": "08:00", "engagement_score": 65.0},
        ]
        recs = BestTimeToPostService.get_recommendations(
            self.discipline_brand,
            platform="linkedin",
            historical_records=history,
        )
        self.assertGreater(len(recs), 0)
        top_rec = recs[0]
        self.assertEqual(top_rec["evidence_type"], "historical_performance")
        self.assertFalse(top_rec["is_preliminary"])
        self.assertIn("14:00", top_rec["suggested_time"])  # Highest score 92.0

    def test_scheduling_intelligence_strategy(self):
        strategy = SchedulingIntelligenceService.generate_strategy(self.discipline_brand)
        self.assertEqual(strategy["archetype"], "discipline_coach")
        self.assertEqual(strategy["recommended_posts_per_week"], 5)
        self.assertEqual(strategy["min_gap_hours"], 18)
        self.assertIn("mindset_habits", [c["category"] for c in strategy["category_distribution"]])

        retail_strategy = SchedulingIntelligenceService.generate_strategy(self.retail_brand)
        self.assertEqual(retail_strategy["archetype"], "retail")
        self.assertEqual(retail_strategy["recommended_posts_per_week"], 6)
        self.assertIn("product_showcase", [c["category"] for c in retail_strategy["category_distribution"]])

    def test_conflict_detector_identifies_past_dates(self):
        past_time = (datetime.now(dt_timezone.utc) - timedelta(hours=2)).isoformat()
        res = SchedulingConflictDetector.detect_conflicts(
            proposed_slot={"scheduled_at": past_time, "platform": "linkedin"},
            existing_posts=[],
        )
        self.assertTrue(res["has_conflicts"])
        self.assertTrue(any(c["code"] == "PAST_DATE_ERROR" for c in res["conflicts"]))

    def test_conflict_detector_identifies_account_collisions(self):
        future_time = (datetime.now(dt_timezone.utc) + timedelta(days=2)).isoformat()
        close_time = (datetime.now(dt_timezone.utc) + timedelta(days=2, minutes=15)).isoformat()

        existing = [{
            "id": 101,
            "scheduled_at": future_time,
            "platform": "linkedin",
            "social_account_id": 5,
            "status": "scheduled",
        }]

        res = SchedulingConflictDetector.detect_conflicts(
            proposed_slot={"scheduled_at": close_time, "platform": "linkedin", "social_account_id": 5},
            existing_posts=existing,
            min_gap_minutes=60,
        )
        self.assertTrue(res["has_conflicts"] or res["has_warnings"])
        self.assertTrue(any(c["code"] == "ACCOUNT_COLLISION" for c in res["conflicts"]))

    def test_conflict_detector_checks_blackout_periods(self):
        future_dt = datetime.now(dt_timezone.utc) + timedelta(days=3)
        day_name = future_dt.strftime("%A").lower()
        settings = {"blackout_periods": [{"day": day_name}]}

        res = SchedulingConflictDetector.detect_conflicts(
            proposed_slot={"scheduled_at": future_dt.isoformat(), "platform": "twitter"},
            existing_posts=[],
            brand_settings=settings,
        )
        self.assertTrue(any(c["code"] == "BLACKOUT_DAY" for c in res["conflicts"]))

    def test_autopilot_strategy_respects_manual_mode(self):
        plan = AutopilotStrategyService.evaluate_plan(
            brand_payload=self.discipline_brand,
            autopilot_settings={"is_enabled": True, "mode": "manual"},
            rules=[],
            pending_items=[{"id": 1, "platform": "linkedin"}],
            existing_scheduled_posts=[],
        )
        self.assertEqual(plan["status"], "manual_mode")
        self.assertEqual(len(plan["actions"]), 0)

    def test_autopilot_strategy_ai_assisted_proposes_slots(self):
        plan = AutopilotStrategyService.evaluate_plan(
            brand_payload=self.discipline_brand,
            autopilot_settings={"is_enabled": True, "mode": "ai_assisted", "posts_per_week": 3},
            rules=[],
            pending_items=[{"id": 1, "platform": "linkedin"}, {"id": 2, "platform": "youtube"}],
            existing_scheduled_posts=[],
        )
        self.assertEqual(plan["status"], "success")
        self.assertEqual(len(plan["actions"]), 2)
        self.assertTrue(all(a["status"] == "proposed_pending_user_confirmation" for a in plan["actions"]))

    def test_autopilot_strategy_automatic_enforces_approval(self):
        # Item not approved with requires_approval=True
        plan = AutopilotStrategyService.evaluate_plan(
            brand_payload=self.discipline_brand,
            autopilot_settings={"is_enabled": True, "mode": "automatic", "requires_approval": True, "posts_per_week": 3},
            rules=[],
            pending_items=[{"id": 10, "is_approved": False, "platform": "linkedin"}],
            existing_scheduled_posts=[],
        )
        self.assertEqual(plan["actions"][0]["status"], "awaiting_approval")

        # Item approved with requires_approval=True
        plan_approved = AutopilotStrategyService.evaluate_plan(
            brand_payload=self.discipline_brand,
            autopilot_settings={"is_enabled": True, "mode": "automatic", "requires_approval": True, "posts_per_week": 3},
            rules=[],
            pending_items=[{"id": 11, "is_approved": True, "platform": "linkedin"}],
            existing_scheduled_posts=[],
        )
        self.assertEqual(plan_approved["actions"][0]["status"], "ready_to_persist")

    def test_content_distribution_spacing(self):
        drafts = [
            {"id": 1, "title": "Draft 1", "platform": "linkedin"},
            {"id": 2, "title": "Draft 2", "platform": "linkedin"},
            {"id": 3, "title": "Draft 3", "platform": "instagram"},
        ]
        dist = ContentDistributionService.distribute(
            brand_payload=self.discipline_brand,
            drafts=drafts,
            min_gap_hours=12,
        )
        self.assertEqual(dist["total_items"], 3)
        self.assertEqual(len(dist["distribution"]), 3)


if __name__ == "__main__":
    unittest.main()
