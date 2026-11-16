from __future__ import annotations

from typing import Any
from .best_time_to_post import BestTimeToPostService


class SchedulingIntelligenceService:
    """Generates comprehensive brand-specific scheduling strategy and distribution intelligence."""

    @classmethod
    def generate_strategy(cls, brand_payload: dict[str, Any]) -> dict[str, Any]:
        """Generate tailored strategy including recommended frequency, platform matrix, and category mix."""
        brand_name = str(brand_payload.get("name") or "Brand").strip()
        category_combined = " ".join([
            str(brand_payload.get("niche") or ""),
            str(brand_payload.get("category") or ""),
            str(brand_payload.get("industry") or ""),
            str(brand_payload.get("name") or ""),
        ]).lower()

        settings = brand_payload.get("settings") or {}
        connected_platforms = (
            brand_payload.get("connected_platforms")
            or settings.get("preferred_platforms")
            or ["linkedin", "instagram"]
        )
        if isinstance(connected_platforms, str):
            connected_platforms = [p.strip() for p in connected_platforms.split(",") if p.strip()]

        timezone = str(
            brand_payload.get("timezone")
            or settings.get("timezone")
            or "UTC"
        )

        archetype = BestTimeToPostService._detect_archetype(category_combined)

        # 1. Recommended frequency
        frequency_map = {
            "discipline_coach": {"posts_per_week": 5, "min_gap_hours": 18, "preferred_days": ["monday", "tuesday", "wednesday", "thursday", "friday"]},
            "fitness": {"posts_per_week": 4, "min_gap_hours": 24, "preferred_days": ["monday", "wednesday", "friday", "saturday"]},
            "retail": {"posts_per_week": 6, "min_gap_hours": 12, "preferred_days": ["wednesday", "thursday", "friday", "saturday", "sunday"]},
            "education": {"posts_per_week": 3, "min_gap_hours": 36, "preferred_days": ["tuesday", "thursday", "saturday"]},
            "corporate": {"posts_per_week": 3, "min_gap_hours": 24, "preferred_days": ["tuesday", "wednesday", "thursday"]},
            "general": {"posts_per_week": 3, "min_gap_hours": 24, "preferred_days": ["monday", "wednesday", "friday"]},
        }
        freq = frequency_map.get(archetype, frequency_map["general"])

        # 2. Content category mix distribution
        category_mix_map = {
            "discipline_coach": [
                {"category": "mindset_habits", "percentage": 40, "description": "Daily accountability, mental frameworks, stoic discipline"},
                {"category": "actionable_systems", "percentage": 30, "description": "Routines, execution guides, habit trackers"},
                {"category": "case_studies", "percentage": 20, "description": "Transformation breakdowns and member milestones"},
                {"category": "community_prompt", "percentage": 10, "description": "Weekly reflection and community check-in"},
            ],
            "retail": [
                {"category": "product_showcase", "percentage": 40, "description": "Feature highlights, high-res media, styling tips"},
                {"category": "social_proof", "percentage": 25, "description": "Customer reviews, unboxing, user-generated content"},
                {"category": "lifestyle", "percentage": 20, "description": "Behind-the-scenes, brand culture, aspirational visuals"},
                {"category": "promotions", "percentage": 15, "description": "Limited-time offers, flash deals, seasonal drops"},
            ],
            "corporate": [
                {"category": "thought_leadership", "percentage": 45, "description": "Industry insights, macro trends, executive perspectives"},
                {"category": "educational", "percentage": 30, "description": "How-to guides, infographics, framework breakdowns"},
                {"category": "company_culture", "percentage": 15, "description": "Team highlights, hiring milestones, core values"},
                {"category": "product_announcements", "percentage": 10, "description": "Feature updates and partner integrations"},
            ],
            "general": [
                {"category": "educational", "percentage": 40, "description": "Helpful tips, tutorials, and insights"},
                {"category": "engagement", "percentage": 30, "description": "Questions, polls, and interactive thoughts"},
                {"category": "brand_authority", "percentage": 20, "description": "Expert commentary and achievements"},
                {"category": "promotional", "percentage": 10, "description": "Announcements and product links"},
            ],
        }
        category_mix = category_mix_map.get(archetype, category_mix_map["general"])

        # 3. Platform priorities based on brand
        platform_priorities = []
        for p in connected_platforms:
            p_clean = p.lower().strip()
            score = 70
            if archetype == "corporate" and p_clean == "linkedin":
                score = 95
            elif archetype in ("retail", "fitness") and p_clean == "instagram":
                score = 95
            elif archetype == "discipline_coach" and p_clean in ("linkedin", "youtube"):
                score = 90
            elif p_clean == "tiktok" and archetype in ("retail", "fitness"):
                score = 85
            platform_priorities.append({"platform": p_clean, "priority_score": score})

        platform_priorities.sort(key=lambda x: x["priority_score"], reverse=True)

        # 4. Best times per platform
        best_times = BestTimeToPostService.get_recommendations(
            brand_payload=brand_payload,
            timezone=timezone,
        )

        return {
            "brand_name": brand_name,
            "archetype": archetype,
            "timezone": timezone,
            "recommended_posts_per_week": freq["posts_per_week"],
            "min_gap_hours": freq["min_gap_hours"],
            "recommended_publishing_days": freq["preferred_days"],
            "category_distribution": category_mix,
            "platform_priorities": platform_priorities,
            "best_times": best_times,
            "rationale": (
                f"Strategy dynamically tailored for {brand_name} ({archetype.replace('_', ' ').title()}). "
                f"Targets {freq['posts_per_week']} posts/week with minimum {freq['min_gap_hours']}h spacing "
                f"to prevent audience fatigue and maximize algorithmic reach in {timezone}."
            ),
        }
