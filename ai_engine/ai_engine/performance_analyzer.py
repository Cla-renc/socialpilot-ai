"""Performance Analyzer for SocialPilot AI (Phase 10)
Analyzes historical post performance and classifies content based on empirical distributions.
No metrics or engagement statistics are fabricated.
"""
from __future__ import annotations

import statistics
from typing import Any


class PerformanceAnalyzer:
    """Analyzes empirical performance distributions across social posts."""

    MIN_POSTS_FOR_PERCENTILES = 3

    def analyze_posts(self, posts: list[dict[str, Any]]) -> dict[str, Any]:
        """Classify posts into performance tiers based on observed interactions."""
        if not posts:
            return {
                "status": "insufficient_data",
                "message": "No historical post analytics provided.",
                "total_posts": 0,
                "top_performing": [],
                "underperforming": [],
                "average_metrics": {},
                "distribution": {},
            }

        scored_posts = []
        for post in posts:
            # Platform-neutral interaction score: views + impressions + (likes*2) + (comments*3) + (shares*4) + (saves*4)
            views = float(post.get("views") or 0.0)
            impressions = float(post.get("impressions") or 0.0)
            reach = float(post.get("reach") or 0.0)
            likes = float(post.get("likes") or 0.0)
            comments = float(post.get("comments") or 0.0)
            shares = float(post.get("shares") or 0.0)
            saves = float(post.get("saves") or 0.0)

            score = (
                views * 0.1
                + impressions * 0.2
                + (likes * 1.0)
                + (comments * 2.0)
                + (shares * 3.0)
                + (saves * 3.0)
            )

            eng_rate = (
                ((likes + comments + shares + saves) / reach * 100)
                if reach > 0
                else (((likes + comments + shares + saves) / impressions * 100) if impressions > 0 else 0.0)
            )

            scored_posts.append({
                **post,
                "calculated_score": round(score, 2),
                "calculated_engagement_rate": round(eng_rate, 2),
            })

        # Sort descending
        scored_posts.sort(key=lambda x: x["calculated_score"], reverse=True)
        count = len(scored_posts)

        if count < self.MIN_POSTS_FOR_PERCENTILES:
            return {
                "status": "limited_data",
                "message": f"Only {count} post(s) analyzed. Percentile classification requires at least {self.MIN_POSTS_FOR_PERCENTILES} observations.",
                "total_posts": count,
                "top_performing": scored_posts[:1],
                "underperforming": scored_posts[-1:] if count > 1 else [],
                "average_metrics": self._compute_averages(scored_posts),
                "distribution": {"sample_size": count},
            }

        # Top 25% (or at least top 1), bottom 25%
        top_slice = max(1, count // 4)
        under_slice = max(1, count // 4)

        top_posts = scored_posts[:top_slice]
        under_posts = scored_posts[-under_slice:]

        scores = [p["calculated_score"] for p in scored_posts]
        dist = {
            "sample_size": count,
            "min_score": min(scores),
            "max_score": max(scores),
            "median_score": round(statistics.median(scores), 2),
            "mean_score": round(statistics.mean(scores), 2),
            "std_dev": round(statistics.stdev(scores), 2) if count > 1 else 0.0,
        }

        return {
            "status": "success",
            "total_posts": count,
            "top_performing": top_posts,
            "underperforming": under_posts,
            "average_metrics": self._compute_averages(scored_posts),
            "distribution": dist,
        }

    def _compute_averages(self, posts: list[dict[str, Any]]) -> dict[str, float]:
        if not posts:
            return {}
        n = len(posts)
        return {
            "avg_views": round(sum(float(p.get("views") or 0.0) for p in posts) / n, 2),
            "avg_impressions": round(sum(float(p.get("impressions") or 0.0) for p in posts) / n, 2),
            "avg_reach": round(sum(float(p.get("reach") or 0.0) for p in posts) / n, 2),
            "avg_likes": round(sum(float(p.get("likes") or 0.0) for p in posts) / n, 2),
            "avg_comments": round(sum(float(p.get("comments") or 0.0) for p in posts) / n, 2),
            "avg_shares": round(sum(float(p.get("shares") or 0.0) for p in posts) / n, 2),
            "avg_engagement_rate": round(sum(float(p.get("calculated_engagement_rate") or 0.0) for p in posts) / n, 2),
        }
