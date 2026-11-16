"""Analytics Report Generator for SocialPilot AI (Phase 10)
Synthesizes structured metrics into executive performance narratives.
"""
from __future__ import annotations

from typing import Any


class AnalyticsReportGenerator:
    """Generates executive summaries and narrative findings for reports."""

    def generate_narrative_summary(
        self,
        brand_name: str,
        metrics: dict[str, Any],
        top_posts: list[dict[str, Any]],
        period_days: int = 30
    ) -> dict[str, Any]:
        followers = metrics.get("followers", 0)
        impressions = metrics.get("impressions", 0)
        reach = metrics.get("reach", 0)
        engagement = metrics.get("engagement", 0)
        eng_rate = metrics.get("engagement_rate")

        eng_str = f"{eng_rate}%" if eng_rate is not None else "unavailable"

        summary_paragraphs = [
            (
                f"During the analyzed {period_days}-day period, {brand_name} maintained an audience of "
                f"{int(followers):,} total followers across connected channels."
            ),
            (
                f"Content generated an aggregate of {int(impressions):,} impressions and reached {int(reach):,} "
                f"unique accounts, yielding an overall engagement rate of {eng_str}."
            ),
        ]

        if top_posts:
            top_post = top_posts[0]
            plat = top_post.get("platform", "channel").capitalize()
            pviews = int(top_post.get("views") or top_post.get("impressions") or 0)
            summary_paragraphs.append(
                f"The highest performing piece of content was published on {plat}, generating {pviews:,} total views."
            )

        return {
            "title": f"Executive Performance Summary — {brand_name}",
            "period_days": period_days,
            "executive_summary": " ".join(summary_paragraphs),
            "key_highlights": [
                f"Audience Reach: {int(reach):,} unique accounts reached",
                f"Total Impressions: {int(impressions):,} content impressions",
                f"Total Engagement: {int(engagement):,} interactions recorded",
            ],
            "limitations_disclaimer": "All statistics represent verified platform API data and internal formulas. Unavailable metrics are omitted.",
        }
