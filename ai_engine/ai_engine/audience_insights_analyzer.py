"""Audience Insights Analyzer for SocialPilot AI (Phase 10)
Analyzes verified demographic and geographical distributions from official platform APIs.
Never fabricates missing demographic statistics.
"""
from __future__ import annotations

from typing import Any


class AudienceInsightsAnalyzer:
    """Analyzes empirical audience breakdowns."""

    def analyze_audience_breakdown(self, dimensions: list[dict[str, Any]]) -> dict[str, Any]:
        if not dimensions:
            return {
                "status": "unavailable",
                "message": "No audience demographic data available from connected platform accounts.",
                "countries": [],
                "age_gender": [],
                "top_markets": [],
            }

        country_data: dict[str, float] = {}
        age_gender_data: dict[str, float] = {}

        for item in dimensions:
            itype = item.get("insight_type", "")
            val = float(item.get("metric_value") or 0.0)
            dim_val = str(item.get("dimension_value", ""))

            if itype == "country":
                country_data[dim_val] = country_data.get(dim_val, 0.0) + val
            elif itype == "age_gender":
                age_gender_data[dim_val] = age_gender_data.get(dim_val, 0.0) + val

        # Sort countries descending
        sorted_countries = sorted(country_data.items(), key=lambda x: x[1], reverse=True)
        total_country_count = sum(country_data.values())

        top_markets = [
            {
                "country_code": c,
                "count": int(v),
                "percentage": round((v / total_country_count) * 100, 1) if total_country_count > 0 else 0.0,
            }
            for c, v in sorted_countries[:5]
        ]

        total_ag = sum(age_gender_data.values())
        age_gender_breakdown = [
            {
                "bracket": b,
                "count": int(v),
                "percentage": round((v / total_ag) * 100, 1) if total_ag > 0 else 0.0,
            }
            for b, v in sorted(age_gender_data.items(), key=lambda x: x[1], reverse=True)
        ]

        return {
            "status": "success",
            "total_audience_sample": int(max(total_country_count, total_ag)),
            "top_markets": top_markets,
            "age_gender_distribution": age_gender_breakdown,
        }
