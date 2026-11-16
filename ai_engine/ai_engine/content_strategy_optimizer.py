"""Content Strategy Optimizer for SocialPilot AI (Phase 10)
Analyzes format, category, and length attributes against actual performance.
"""
from __future__ import annotations

from typing import Any


class ContentStrategyOptimizer:
    """Compares content attributes (formats, categories, lengths) with engagement outcomes."""

    def compare_formats_and_categories(self, posts: list[dict[str, Any]]) -> dict[str, Any]:
        if not posts:
            return {
                "status": "insufficient_data",
                "format_performance": {},
                "category_performance": {},
            }

        formats: dict[str, list[float]] = {}
        categories: dict[str, list[float]] = {}

        for p in posts:
            fmt = str(p.get("content_format") or p.get("format") or "standard_post").lower()
            cat = str(p.get("category") or p.get("content_category") or "general").lower()

            likes = float(p.get("likes") or 0.0)
            comments = float(p.get("comments") or 0.0)
            shares = float(p.get("shares") or 0.0)
            score = likes + (comments * 2.0) + (shares * 3.0)

            formats.setdefault(fmt, []).append(score)
            categories.setdefault(cat, []).append(score)

        fmt_summary = {
            f: {
                "count": len(scores),
                "avg_score": round(sum(scores) / len(scores), 2),
            }
            for f, scores in formats.items()
        }

        cat_summary = {
            c: {
                "count": len(scores),
                "avg_score": round(sum(scores) / len(scores), 2),
            }
            for c, scores in categories.items()
        }

        return {
            "status": "success",
            "posts_analyzed": len(posts),
            "format_performance": fmt_summary,
            "category_performance": cat_summary,
        }
