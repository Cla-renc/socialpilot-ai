"""Engagement Analysis Module for SocialPilot AI (Phase 10)
Analyzes interaction depth, conversation ratios, amplification, and saves.
"""
from __future__ import annotations

from typing import Any


class EngagementAnalyzer:
    """Calculates engagement quality metrics from real post analytics."""

    def analyze_engagement_quality(self, posts: list[dict[str, Any]]) -> dict[str, Any]:
        if not posts:
            return {
                "status": "insufficient_data",
                "message": "No post data available for engagement quality analysis.",
                "total_interactions": 0,
            }

        total_likes = sum(float(p.get("likes") or 0.0) for p in posts)
        total_comments = sum(float(p.get("comments") or 0.0) for p in posts)
        total_shares = sum(float(p.get("shares") or 0.0) for p in posts)
        total_saves = sum(float(p.get("saves") or 0.0) for p in posts)
        total_interactions = total_likes + total_comments + total_shares + total_saves

        if total_interactions == 0:
            return {
                "status": "zero_engagement",
                "message": "Zero interactions recorded across the analyzed posts.",
                "total_interactions": 0,
                "breakdown": {
                    "likes_pct": 0.0,
                    "comments_pct": 0.0,
                    "shares_pct": 0.0,
                    "saves_pct": 0.0,
                },
                "interaction_depth_ratio": 0.0,
            }

        # Interaction Depth Ratio: (Comments + Shares + Saves) / Total Interactions
        # Indicates proportion of high-intent actions vs passive likes
        deep_interactions = total_comments + total_shares + total_saves
        depth_ratio = round((deep_interactions / total_interactions) * 100, 2)

        return {
            "status": "success",
            "posts_analyzed": len(posts),
            "total_interactions": int(total_interactions),
            "breakdown": {
                "likes": int(total_likes),
                "likes_pct": round((total_likes / total_interactions) * 100, 1),
                "comments": int(total_comments),
                "comments_pct": round((total_comments / total_interactions) * 100, 1),
                "shares": int(total_shares),
                "shares_pct": round((total_shares / total_interactions) * 100, 1),
                "saves": int(total_saves),
                "saves_pct": round((total_saves / total_interactions) * 100, 1),
            },
            "interaction_depth_ratio": depth_ratio,
            "conversation_to_reaction_ratio": round(total_comments / max(1.0, total_likes), 3),
            "amplification_rate": round(total_shares / max(1.0, total_likes), 3),
        }
