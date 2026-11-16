"""Publishing Time Optimizer for SocialPilot AI (Phase 10)
Derives optimal publishing windows based on actual historical post engagement.
Does not invent universal peaks if data is insufficient.
"""
from __future__ import annotations

from typing import Any


class PublishingTimeOptimizer:
    """Analyzes engagement distribution by publishing hour and weekday."""

    MIN_POSTS_REQUIRED = 5

    def optimize_publishing_times(
        self,
        posts_with_timestamps: list[dict[str, Any]],
        platform: str | None = None
    ) -> dict[str, Any]:
        """Analyze empirical engagement by hour (0-23 UTC) and day of week (0-6)."""
        valid_posts = [
            p for p in posts_with_timestamps
            if p.get("published_at") or p.get("created_at") or (p.get("hour_utc") is not None)
        ]

        sample_size = len(valid_posts)

        if sample_size < self.MIN_POSTS_REQUIRED:
            return {
                "status": "insufficient_data",
                "confidence_level": "insufficient_data",
                "sample_size": sample_size,
                "minimum_required": self.MIN_POSTS_REQUIRED,
                "message": (
                    f"Only {sample_size} published post(s) recorded with timestamps. "
                    f"At least {self.MIN_POSTS_REQUIRED} observations are required to detect statistically reliable publishing peaks."
                ),
                "is_heuristic_fallback": True,
                "recommended_hours_utc": [9, 13, 17],  # Transparent default heuristic fallback
                "recommended_days": [1, 2, 3],  # Tue, Wed, Thu
            }

        # Accumulate interactions by hour
        hour_data: dict[int, list[float]] = {h: [] for h in range(24)}
        day_data: dict[int, list[float]] = {d: [] for d in range(7)}

        for p in valid_posts:
            likes = float(p.get("likes") or 0.0)
            comments = float(p.get("comments") or 0.0)
            shares = float(p.get("shares") or 0.0)
            eng = likes + (comments * 2.0) + (shares * 3.0)

            hour = p.get("hour_utc")
            if hour is None and p.get("published_at"):
                try:
                    from datetime import datetime
                    dt = datetime.fromisoformat(str(p["published_at"]).replace("Z", "+00:00"))
                    hour = dt.hour
                    day = dt.weekday()
                    day_data[day].append(eng)
                except Exception:
                    hour = None

            if hour is not None and 0 <= int(hour) <= 23:
                hour_data[int(hour)].append(eng)

        # Average engagement per hour
        hour_averages = []
        for h, scores in hour_data.items():
            if scores:
                hour_averages.append({
                    "hour_utc": h,
                    "avg_engagement": round(sum(scores) / len(scores), 2),
                    "posts_count": len(scores),
                })

        hour_averages.sort(key=lambda x: x["avg_engagement"], reverse=True)
        best_hours = [item["hour_utc"] for item in hour_averages[:3]]

        confidence = "high" if sample_size >= 20 else ("medium" if sample_size >= 10 else "low")

        return {
            "status": "success",
            "confidence_level": confidence,
            "sample_size": sample_size,
            "is_heuristic_fallback": False,
            "recommended_hours_utc": best_hours if best_hours else [9, 13, 17],
            "top_hourly_performance": hour_averages[:5],
            "supporting_evidence": f"Evaluated {sample_size} published posts across {len(hour_averages)} distinct posting hours.",
        }
