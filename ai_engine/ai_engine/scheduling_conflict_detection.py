from __future__ import annotations

from datetime import datetime, timezone as dt_timezone
from typing import Any


class SchedulingConflictDetector:
    """Detects scheduling collisions, minimum gap violations, blackout overlaps, and past dates."""

    @classmethod
    def detect_conflicts(
        cls,
        proposed_slot: dict[str, Any],
        existing_posts: list[dict[str, Any]],
        brand_settings: dict[str, Any] | None = None,
        min_gap_minutes: int = 60,
    ) -> dict[str, Any]:
        """Evaluate a proposed scheduled post against existing schedule and constraints."""
        conflicts: list[dict[str, Any]] = []

        proposed_time_raw = proposed_slot.get("scheduled_at")
        proposed_platform = str(proposed_slot.get("platform", "")).lower().strip()
        proposed_account_id = proposed_slot.get("social_account_id")

        if not proposed_time_raw:
            conflicts.append({
                "code": "MISSING_TIMESTAMP",
                "severity": "error",
                "message": "Scheduled time timestamp is required.",
                "suggested_resolution": "Specify a valid ISO 8601 date and time.",
            })
            return {"has_conflicts": True, "conflicts": conflicts}

        # Parse proposed time
        try:
            proposed_dt = datetime.fromisoformat(str(proposed_time_raw).replace("Z", "+00:00"))
            if proposed_dt.tzinfo is None:
                proposed_dt = proposed_dt.replace(tzinfo=dt_timezone.utc)
        except Exception as e:
            conflicts.append({
                "code": "INVALID_TIMESTAMP",
                "severity": "error",
                "message": f"Invalid timestamp format: {e}",
                "suggested_resolution": "Provide a valid ISO date time string.",
            })
            return {"has_conflicts": True, "conflicts": conflicts}

        now_utc = datetime.now(dt_timezone.utc)

        # 1. Past date check
        if proposed_dt < now_utc:
            conflicts.append({
                "code": "PAST_DATE_ERROR",
                "severity": "error",
                "message": "Cannot schedule a post for a time in the past.",
                "suggested_resolution": "Select a future date and time.",
            })

        # 2. Check collision against existing posts
        for ep in existing_posts:
            # Skip cancelled posts
            if str(ep.get("status", "")).lower() == "cancelled":
                continue

            ep_time_raw = ep.get("scheduled_at") or ep.get("scheduled_for")
            if not ep_time_raw:
                continue

            try:
                ep_dt = datetime.fromisoformat(str(ep_time_raw).replace("Z", "+00:00"))
                if ep_dt.tzinfo is None:
                    ep_dt = ep_dt.replace(tzinfo=dt_timezone.utc)
            except Exception:
                continue

            ep_platform = str(ep.get("platform", "")).lower().strip()
            ep_account_id = ep.get("social_account_id")

            # Check exact collision
            diff_seconds = abs((proposed_dt - ep_dt).total_seconds())

            # Collision condition A: Exact same social account within buffer
            if proposed_account_id and ep_account_id and str(proposed_account_id) == str(ep_account_id):
                if diff_seconds < (min_gap_minutes * 60):
                    conflicts.append({
                        "code": "ACCOUNT_COLLISION",
                        "severity": "error" if diff_seconds < 300 else "warning",
                        "existing_post_id": ep.get("id"),
                        "message": (
                            f"Account #{proposed_account_id} already has a post scheduled at {ep_dt.isoformat()} "
                            f"(within {int(diff_seconds / 60)} minutes)."
                        ),
                        "suggested_resolution": f"Reschedule to at least {min_gap_minutes} minutes after existing post.",
                    })

            # Collision condition B: Exact same platform on brand within buffer
            elif proposed_platform and ep_platform and proposed_platform == ep_platform:
                if diff_seconds < (min_gap_minutes * 60):
                    conflicts.append({
                        "code": "PLATFORM_COLLISION",
                        "severity": "warning",
                        "existing_post_id": ep.get("id"),
                        "message": (
                            f"Platform '{proposed_platform}' already has a post scheduled around {ep_dt.isoformat()}."
                        ),
                        "suggested_resolution": "Space posts across different days or hours to avoid audience saturation.",
                    })

        # 3. Blackout periods check
        settings = brand_settings or {}
        blackouts = settings.get("blackout_periods") or []
        weekday_name = proposed_dt.strftime("%A").lower()
        hour = proposed_dt.hour

        for b in blackouts:
            if isinstance(b, dict):
                # Day blackout
                if b.get("day") and str(b.get("day")).lower() == weekday_name:
                    conflicts.append({
                        "code": "BLACKOUT_DAY",
                        "severity": "warning",
                        "message": f"Brand configuration has designated {weekday_name.capitalize()} as a blackout day.",
                        "suggested_resolution": "Choose another day of the week.",
                    })
                # Hour range blackout
                start_h = b.get("start_hour")
                end_h = b.get("end_hour")
                if start_h is not None and end_h is not None:
                    if start_h <= hour <= end_h or (start_h > end_h and (hour >= start_h or hour <= end_h)):
                        conflicts.append({
                            "code": "BLACKOUT_HOURS",
                            "severity": "warning",
                            "message": f"Scheduled hour {hour}:00 falls in brand blackout window ({start_h}:00 - {end_h}:00).",
                            "suggested_resolution": "Select a time within standard active hours.",
                        })

        return {
            "has_conflicts": len([c for c in conflicts if c.get("severity") == "error"]) > 0,
            "has_warnings": len([c for c in conflicts if c.get("severity") == "warning"]) > 0,
            "conflict_count": len(conflicts),
            "conflicts": conflicts,
        }
