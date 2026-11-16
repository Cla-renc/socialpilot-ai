from __future__ import annotations

from typing import Any
from .best_time_to_post import BestTimeToPostService
from .scheduling_intelligence import SchedulingIntelligenceService


class PublishingScheduleBuilder:
    """Constructs structured weekly publishing schedules and calendar proposals."""

    @classmethod
    def build_weekly_proposal(
        cls,
        brand_payload: dict[str, Any],
        timezone: str = "UTC",
    ) -> dict[str, Any]:
        """Construct structured weekly proposal for a brand."""
        strategy = SchedulingIntelligenceService.generate_strategy(brand_payload)
        posts_per_week = strategy["recommended_posts_per_week"]
        days = strategy["recommended_publishing_days"][:posts_per_week]
        best_times = BestTimeToPostService.get_recommendations(brand_payload, timezone=timezone)

        slots = []
        for i, day in enumerate(days):
            slot_rec = best_times[i % len(best_times)] if best_times else None
            slots.append({
                "slot_number": i + 1,
                "day_of_week": day,
                "suggested_time": slot_rec.get("suggested_time", "10:00") if slot_rec else "10:00",
                "platform": slot_rec.get("platform", "linkedin") if slot_rec else "linkedin",
                "timezone": timezone,
                "reason": slot_rec.get("reason", "Heuristic distribution") if slot_rec else "Weekly distribution",
                "evidence_type": slot_rec.get("evidence_type", "heuristic_preliminary") if slot_rec else "heuristic_preliminary",
            })

        return {
            "brand_name": brand_payload.get("name", "Brand"),
            "weekly_slots_count": len(slots),
            "timezone": timezone,
            "slots": slots,
            "strategy_summary": strategy["rationale"],
        }
