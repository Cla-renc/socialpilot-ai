"""Content Performance Predictor for SocialPilot AI (Phase 10)
Generates heuristic predictive estimates explicitly labeled as estimates.
Never claims statistical certainty or fabricates real data.
"""
from __future__ import annotations

from typing import Any


class ContentPerformancePredictor:
    """Provides estimated performance ranges based on brand historical baselines."""

    def predict_content_outcome(
        self,
        draft_content: dict[str, Any],
        historical_posts: list[dict[str, Any]],
        platform: str = "linkedin"
    ) -> dict[str, Any]:
        """Generate an explicitly labeled predictive estimate."""
        plat_posts = [p for p in historical_posts if str(p.get("platform", "")).lower() == platform.lower()]
        count = len(plat_posts)

        if count < 3:
            return {
                "is_estimate": True,
                "status": "insufficient_history",
                "label": "PREDICTIVE ESTIMATE (LOW CONFIDENCE)",
                "confidence_level": "insufficient_data",
                "message": (
                    f"Insufficient historical data ({count} post(s) found for {platform}). "
                    "Predictive estimates require at least 3 historical posts on this platform."
                ),
                "estimated_views_range": None,
                "estimated_engagement_range": None,
                "historical_baseline_sample": count,
            }

        views = [float(p.get("views") or p.get("impressions") or 0.0) for p in plat_posts]
        engs = [
            float(p.get("likes") or 0.0)
            + float(p.get("comments") or 0.0) * 2.0
            + float(p.get("shares") or 0.0) * 3.0
            for p in plat_posts
        ]

        avg_views = sum(views) / max(1, len(views))
        avg_eng = sum(engs) / max(1, len(engs))

        # Text features heuristic: length, question hook, hashtags
        text = str(draft_content.get("text") or draft_content.get("caption") or draft_content.get("body") or "")
        multiplier = 1.0
        if "?" in text:
            multiplier += 0.1  # Question hooks historically correlate with higher comments
        if "#" in text:
            multiplier += 0.05

        est_views_low = int(max(0, avg_views * 0.75 * multiplier))
        est_views_high = int(avg_views * 1.35 * multiplier)

        est_eng_low = int(max(0, avg_eng * 0.70 * multiplier))
        est_eng_high = int(avg_eng * 1.40 * multiplier)

        conf = "medium" if count >= 10 else "low"

        return {
            "is_estimate": True,
            "status": "success",
            "label": f"PREDICTIVE ESTIMATE ({conf.upper()} CONFIDENCE)",
            "confidence_level": conf,
            "historical_baseline_sample": count,
            "platform": platform,
            "estimated_views_range": {"min": est_views_low, "max": est_views_high},
            "estimated_engagement_range": {"min": est_eng_low, "max": est_eng_high},
            "estimation_notes": (
                f"Estimate calculated from {count} historical {platform} posts with a baseline average "
                f"of {int(avg_views)} views and {int(avg_eng)} interaction score. Actual outcome will depend on live distribution algorithms."
            ),
        }
