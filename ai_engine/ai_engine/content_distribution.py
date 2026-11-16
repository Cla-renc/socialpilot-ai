from __future__ import annotations

from datetime import datetime, timedelta, timezone as dt_timezone
from typing import Any
from .best_time_to_post import BestTimeToPostService


class ContentDistributionService:
    """Distributes a set of content drafts or queue items across calendar slots."""

    @classmethod
    def distribute(
        cls,
        brand_payload: dict[str, Any],
        drafts: list[dict[str, Any]],
        start_date: str | None = None,
        days_span: int = 14,
        timezone: str = "UTC",
        min_gap_hours: int = 12,
    ) -> dict[str, Any]:
        """Distribute content drafts across days avoiding saturation and clustering."""
        now_utc = datetime.now(dt_timezone.utc)
        start_dt = (
            datetime.fromisoformat(start_date.replace("Z", "+00:00"))
            if start_date
            else now_utc + timedelta(hours=3)
        )

        recommendations = BestTimeToPostService.get_recommendations(
            brand_payload=brand_payload,
            timezone=timezone,
        )

        schedule_items: list[dict[str, Any]] = []
        current_cursor = start_dt

        # Track per-platform last scheduled time to maintain min_gap_hours
        platform_last_scheduled: dict[str, datetime] = {}

        for idx, draft in enumerate(drafts):
            platform = str(draft.get("platform") or "linkedin").lower().strip()

            # Find matching platform recommendations
            plat_recs = [r for r in recommendations if r.get("platform") == platform]
            rec_slot = plat_recs[idx % len(plat_recs)] if plat_recs else None

            suggested_time = rec_slot.get("suggested_time", "10:00") if rec_slot else "10:00"

            # Parse hour and minute
            try:
                parts = suggested_time.split(":")
                h, m = int(parts[0]), int(parts[1])
            except Exception:
                h, m = 10, 0

            # Find next viable date
            candidate_dt = current_cursor.replace(hour=h, minute=m, second=0, microsecond=0)
            if candidate_dt <= current_cursor:
                candidate_dt += timedelta(days=1)

            # Check min gap for this platform
            last_dt = platform_last_scheduled.get(platform)
            if last_dt and (candidate_dt - last_dt).total_seconds() < (min_gap_hours * 3600):
                candidate_dt = last_dt + timedelta(hours=min_gap_hours)

            platform_last_scheduled[platform] = candidate_dt
            current_cursor = candidate_dt + timedelta(hours=4)

            schedule_items.append({
                "draft_id": draft.get("id"),
                "title": draft.get("title") or f"Content Draft #{draft.get('id')}",
                "platform": platform,
                "scheduled_at": candidate_dt.strftime("%Y-%m-%d %H:%M:%S"),
                "timezone": timezone,
                "evidence_type": rec_slot.get("evidence_type", "heuristic_preliminary") if rec_slot else "heuristic_preliminary",
                "reason": rec_slot.get("reason", "Distributed to balance brand calendar.") if rec_slot else "Optimal spacing.",
            })

        return {
            "total_items": len(schedule_items),
            "start_date": start_dt.isoformat(),
            "timezone": timezone,
            "min_gap_hours": min_gap_hours,
            "distribution": schedule_items,
        }
