from __future__ import annotations

from datetime import datetime, timedelta, timezone as dt_timezone
from typing import Any
from .best_time_to_post import BestTimeToPostService


class AutopilotStrategyService:
    """Evaluates autopilot rules and generates execution plans for brand content scheduling.

    Strictly produces scheduling plans for future dates; never attempts publishing.
    """

    @classmethod
    def evaluate_plan(
        cls,
        brand_payload: dict[str, Any],
        autopilot_settings: dict[str, Any],
        rules: list[dict[str, Any]],
        pending_items: list[dict[str, Any]],
        existing_scheduled_posts: list[dict[str, Any]],
        target_week_start: str | None = None,
    ) -> dict[str, Any]:
        """Generate structured autopilot scheduling plan based on brand settings and rules."""
        is_enabled = bool(autopilot_settings.get("is_enabled", False))
        mode = str(autopilot_settings.get("mode", "manual")).lower()
        requires_approval = bool(autopilot_settings.get("requires_approval", True))
        posts_per_week = int(autopilot_settings.get("posts_per_week", 3))
        min_gap_hours = int(autopilot_settings.get("min_gap_hours", 12))
        timezone = str(autopilot_settings.get("timezone", "UTC"))

        if not is_enabled:
            return {
                "status": "disabled",
                "message": "Autopilot is currently disabled for this brand.",
                "mode": mode,
                "actions": [],
                "scheduled_count": 0,
            }

        if mode == "manual":
            return {
                "status": "manual_mode",
                "message": "Brand is in manual scheduling mode. Autopilot will not automatically schedule items.",
                "mode": mode,
                "actions": [],
                "scheduled_count": 0,
            }

        now_utc = datetime.now(dt_timezone.utc)
        start_date = (
            datetime.fromisoformat(target_week_start.replace("Z", "+00:00"))
            if target_week_start
            else now_utc
        )

        # Count already scheduled posts in the target window (next 7 days)
        active_scheduled = [
            p for p in existing_scheduled_posts
            if str(p.get("status", "")).lower() in ("scheduled", "in_queue", "awaiting_approval")
        ]

        quota_remaining = max(0, posts_per_week - len(active_scheduled))

        # Get optimal slots for brand
        best_times = BestTimeToPostService.get_recommendations(
            brand_payload=brand_payload,
            timezone=timezone,
        )

        planned_actions: list[dict[str, Any]] = []
        activity_logs: list[dict[str, Any]] = []

        slot_index = 0
        current_cursor = start_date + timedelta(hours=2)  # Earliest slot at least 2h from now

        for item in pending_items:
            if quota_remaining <= 0:
                planned_actions.append({
                    "item_id": item.get("id"),
                    "action": "skipped_weekly_limit_reached",
                    "reason": f"Weekly quota of {posts_per_week} posts reached.",
                    "status": "skipped",
                })
                continue

            item_approved = bool(item.get("is_approved", False))
            target_platform = item.get("platform") or "linkedin"

            # In AI-assisted mode, always generate proposal for user confirmation
            if mode == "ai_assisted":
                slot = best_times[slot_index % len(best_times)] if best_times else None
                slot_time = slot.get("suggested_time", "10:00") if slot else "10:00"
                slot_day = slot.get("day_of_week", "wednesday") if slot else "wednesday"

                proposed_dt = current_cursor + timedelta(days=(slot_index + 1))
                proposed_iso = proposed_dt.strftime(f"%Y-%m-%d {slot_time}:00")

                action = {
                    "item_id": item.get("id"),
                    "content_draft_id": item.get("content_draft_id") or item.get("id"),
                    "action": "recommend_slot",
                    "target_platform": target_platform,
                    "proposed_scheduled_at": proposed_iso,
                    "timezone": timezone,
                    "reason": f"AI-assisted recommendation: optimal slot for {target_platform} on {slot_day}.",
                    "status": "proposed_pending_user_confirmation",
                    "requires_user_confirmation": True,
                }
                planned_actions.append(action)
                activity_logs.append({
                    "action": "recommended_slot",
                    "details": action,
                    "status": "info",
                })
                slot_index += 1
                current_cursor = proposed_dt + timedelta(hours=min_gap_hours)
                continue

            # Automatic mode
            if mode == "automatic":
                # Check approval requirements
                if requires_approval and not item_approved:
                    action = {
                        "item_id": item.get("id"),
                        "content_draft_id": item.get("content_draft_id") or item.get("id"),
                        "action": "queued_awaiting_approval",
                        "reason": "Brand requires explicit approval before scheduling.",
                        "status": "awaiting_approval",
                    }
                    planned_actions.append(action)
                    activity_logs.append({
                        "action": "held_for_approval",
                        "details": action,
                        "status": "warning",
                    })
                    continue

                # Approved item -> schedule to next slot
                slot = best_times[slot_index % len(best_times)] if best_times else None
                slot_time = slot.get("suggested_time", "10:00") if slot else "10:00"
                proposed_dt = current_cursor + timedelta(days=1)
                proposed_iso = proposed_dt.strftime(f"%Y-%m-%d {slot_time}:00")

                action = {
                    "item_id": item.get("id"),
                    "content_draft_id": item.get("content_draft_id") or item.get("id"),
                    "action": "auto_schedule",
                    "target_platform": target_platform,
                    "scheduled_at": proposed_iso,
                    "timezone": timezone,
                    "status": "ready_to_persist",
                    "reason": f"Autopilot scheduled approved item adhering to {min_gap_hours}h gap.",
                }
                planned_actions.append(action)
                activity_logs.append({
                    "action": "auto_scheduled_item",
                    "details": action,
                    "status": "success",
                })
                quota_remaining -= 1
                slot_index += 1
                current_cursor = proposed_dt + timedelta(hours=min_gap_hours)

        return {
            "status": "success",
            "mode": mode,
            "quota_posts_per_week": posts_per_week,
            "quota_remaining": quota_remaining,
            "actions": planned_actions,
            "activity_logs": activity_logs,
            "scheduled_count": len([a for a in planned_actions if a.get("status") in ("ready_to_persist", "proposed_pending_user_confirmation")]),
        }
