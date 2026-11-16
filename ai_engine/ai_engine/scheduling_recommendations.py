from __future__ import annotations

from typing import Any
from .best_time_to_post import BestTimeToPostService
from .scheduling_intelligence import SchedulingIntelligenceService
from .content_distribution import ContentDistributionService
from .autopilot_strategy import AutopilotStrategyService
from .scheduling_conflict_detection import SchedulingConflictDetector
from .publishing_schedule import PublishingScheduleBuilder


class SchedulingRecommendationCoordinator:
    """Coordinator service aggregating all scheduling intelligence capabilities for internal APIs."""

    @classmethod
    def get_best_times(
        cls,
        brand_payload: dict[str, Any],
        platform: str | None = None,
        timezone: str = "UTC",
        historical_records: list[dict[str, Any]] | None = None,
    ) -> list[dict[str, Any]]:
        return BestTimeToPostService.get_recommendations(
            brand_payload=brand_payload,
            platform=platform,
            timezone=timezone,
            historical_records=historical_records,
        )

    @classmethod
    def get_strategy(cls, brand_payload: dict[str, Any]) -> dict[str, Any]:
        return SchedulingIntelligenceService.generate_strategy(brand_payload)

    @classmethod
    def plan_distribution(
        cls,
        brand_payload: dict[str, Any],
        drafts: list[dict[str, Any]],
        start_date: str | None = None,
        days_span: int = 14,
        timezone: str = "UTC",
        min_gap_hours: int = 12,
    ) -> dict[str, Any]:
        return ContentDistributionService.distribute(
            brand_payload=brand_payload,
            drafts=drafts,
            start_date=start_date,
            days_span=days_span,
            timezone=timezone,
            min_gap_hours=min_gap_hours,
        )

    @classmethod
    def evaluate_autopilot_plan(
        cls,
        brand_payload: dict[str, Any],
        autopilot_settings: dict[str, Any],
        rules: list[dict[str, Any]],
        pending_items: list[dict[str, Any]],
        existing_scheduled_posts: list[dict[str, Any]],
        target_week_start: str | None = None,
    ) -> dict[str, Any]:
        return AutopilotStrategyService.evaluate_plan(
            brand_payload=brand_payload,
            autopilot_settings=autopilot_settings,
            rules=rules,
            pending_items=pending_items,
            existing_scheduled_posts=existing_scheduled_posts,
            target_week_start=target_week_start,
        )

    @classmethod
    def check_conflicts(
        cls,
        proposed_slot: dict[str, Any],
        existing_posts: list[dict[str, Any]],
        brand_settings: dict[str, Any] | None = None,
        min_gap_minutes: int = 60,
    ) -> dict[str, Any]:
        return SchedulingConflictDetector.detect_conflicts(
            proposed_slot=proposed_slot,
            existing_posts=existing_posts,
            brand_settings=brand_settings,
            min_gap_minutes=min_gap_minutes,
        )

    @classmethod
    def build_proposal(
        cls,
        brand_payload: dict[str, Any],
        timezone: str = "UTC",
    ) -> dict[str, Any]:
        return PublishingScheduleBuilder.build_weekly_proposal(
            brand_payload=brand_payload,
            timezone=timezone,
        )
