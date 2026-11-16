"""AI Optimization Recommendations Engine for SocialPilot AI (Phase 10)
Synthesizes performance evidence into actionable recommendations.
Ensures every recommendation includes explicit supporting evidence and honest confidence levels.
"""
from __future__ import annotations

from typing import Any

from .performance_analyzer import PerformanceAnalyzer
from .engagement_analysis import EngagementAnalyzer
from .content_strategy_optimizer import ContentStrategyOptimizer


class OptimizationRecommendationsGenerator:
    """Generates evidence-backed optimization recommendations."""

    MINIMUM_POSTS_THRESHOLD = 3

    def __init__(self) -> None:
        self.perf_analyzer = PerformanceAnalyzer()
        self.eng_analyzer = EngagementAnalyzer()
        self.strategy_optimizer = ContentStrategyOptimizer()

    def generate_recommendations(
        self,
        brand_payload: dict[str, Any],
        posts: list[dict[str, Any]],
        period_days: int = 30
    ) -> list[dict[str, Any]]:
        sample_size = len(posts)

        if sample_size < self.MINIMUM_POSTS_THRESHOLD:
            return [
                {
                    "recommendation_type": "general",
                    "platform": None,
                    "title": "Insufficient Historical Data for Optimization",
                    "description": "Insufficient historical data to generate a reliable optimization recommendation.",
                    "confidence_level": "insufficient_data",
                    "confidence_explanation": f"Observed {sample_size} post(s). A minimum of {self.MINIMUM_POSTS_THRESHOLD} published posts with collected metrics is required.",
                    "supporting_metrics": {
                        "posts_analyzed": sample_size,
                        "minimum_required": self.MINIMUM_POSTS_THRESHOLD,
                        "reporting_period_days": period_days,
                    },
                    "suggested_action": "Publish at least 3-5 posts across connected channels to establish an empirical performance baseline.",
                }
            ]

        recommendations: list[dict[str, Any]] = []

        # 1. Platform Performance Discrepancy
        by_platform: dict[str, list[dict[str, Any]]] = {}
        for p in posts:
            plat = str(p.get("platform") or "unknown").lower()
            by_platform.setdefault(plat, []).append(p)

        if len(by_platform) >= 2:
            plat_scores: dict[str, dict[str, Any]] = {}
            for plat, plist in by_platform.items():
                tot_likes = sum(float(x.get("likes") or 0.0) for x in plist)
                tot_comms = sum(float(x.get("comments") or 0.0) for x in plist)
                tot_shares = sum(float(x.get("shares") or 0.0) for x in plist)
                avg_score = (tot_likes + tot_comms * 2 + tot_shares * 3) / len(plist)
                plat_scores[plat] = {
                    "count": len(plist),
                    "avg_score": round(avg_score, 2),
                }

            sorted_plats = sorted(plat_scores.items(), key=lambda x: x[1]["avg_score"], reverse=True)
            top_plat, top_stat = sorted_plats[0]
            low_plat, low_stat = sorted_plats[-1]

            if top_stat["count"] >= 2 and top_stat["avg_score"] > low_stat["avg_score"] * 1.3:
                conf = "high" if top_stat["count"] >= 5 and low_stat["count"] >= 5 else "medium"
                recommendations.append({
                    "recommendation_type": "format_adjustment",
                    "platform": top_plat,
                    "title": f"Focus Content Cadence on {top_plat.capitalize()}",
                    "description": (
                        f"Your {top_plat.capitalize()} posts achieved an average interaction score of {top_stat['avg_score']}, "
                        f"outperforming {low_plat.capitalize()} ({low_stat['avg_score']}) during the reporting period."
                    ),
                    "confidence_level": conf,
                    "confidence_explanation": f"Based on {top_stat['count']} {top_plat} posts vs {low_stat['count']} {low_plat} posts with strong statistical separation.",
                    "supporting_metrics": {
                        "top_platform": top_plat,
                        "top_platform_avg": top_stat["avg_score"],
                        "lower_platform": low_plat,
                        "lower_platform_avg": low_stat["avg_score"],
                        "posts_analyzed": sample_size,
                    },
                    "suggested_action": f"Increase publishing frequency on {top_plat.capitalize()} to capitalize on superior audience responsiveness.",
                })

        # 2. Conversation vs Broadcast Quality
        eng_summary = self.eng_analyzer.analyze_engagement_quality(posts)
        depth_ratio = eng_summary.get("interaction_depth_ratio", 0.0)

        if depth_ratio < 20.0 and sample_size >= 4:
            recommendations.append({
                "recommendation_type": "caption_style",
                "platform": None,
                "title": "Incorporate Direct Question Hooks to Deepen Engagement",
                "description": (
                    f"Only {depth_ratio}% of interactions were conversational (comments, shares, saves), "
                    f"while {eng_summary.get('breakdown', {}).get('likes_pct', 0)}% were passive likes."
                ),
                "confidence_level": "medium",
                "confidence_explanation": f"Observed low comment-to-like ratio across {sample_size} published posts.",
                "supporting_metrics": {
                    "depth_ratio_pct": depth_ratio,
                    "likes_pct": eng_summary.get("breakdown", {}).get("likes_pct", 0),
                    "posts_analyzed": sample_size,
                },
                "suggested_action": "End upcoming post captions with a specific, opinion-oriented question to stimulate comment threads.",
            })
        elif depth_ratio >= 35.0:
            recommendations.append({
                "recommendation_type": "content_category",
                "platform": None,
                "title": "Maintain High-Intent Discussion Content",
                "description": (
                    f"Strong interaction depth: {depth_ratio}% of actions were active comments and shares, "
                    f"indicating high resonance with the target audience."
                ),
                "confidence_level": "high" if sample_size >= 6 else "medium",
                "confidence_explanation": f"Consistent conversation depth observed across {sample_size} posts.",
                "supporting_metrics": {
                    "depth_ratio_pct": depth_ratio,
                    "posts_analyzed": sample_size,
                },
                "suggested_action": "Continue publishing community-centric prompts and insight frameworks.",
            })

        # 3. Format comparison
        strat = self.strategy_optimizer.compare_formats_and_categories(posts)
        fmt_perf = strat.get("format_performance", {})
        if len(fmt_perf) >= 2:
            sorted_fmts = sorted(fmt_perf.items(), key=lambda x: x[1]["avg_score"], reverse=True)
            best_f, best_f_stat = sorted_fmts[0]
            if best_f_stat["count"] >= 2:
                recommendations.append({
                    "recommendation_type": "format_adjustment",
                    "platform": None,
                    "title": f"Expand '{best_f.replace('_', ' ').capitalize()}' Content Format",
                    "description": (
                        f"Posts formatted as '{best_f.replace('_', ' ')}' averaged {best_f_stat['avg_score']} engagement points, "
                        f"higher than other published content formats."
                    ),
                    "confidence_level": "medium" if best_f_stat["count"] >= 3 else "low",
                    "confidence_explanation": f"Calculated from {best_f_stat['count']} posts using this specific format.",
                    "supporting_metrics": {
                        "best_format": best_f,
                        "format_post_count": best_f_stat["count"],
                        "average_score": best_f_stat["avg_score"],
                    },
                    "suggested_action": f"Test producing 1-2 additional '{best_f.replace('_', ' ')}' pieces in next week's schedule.",
                })

        return recommendations
