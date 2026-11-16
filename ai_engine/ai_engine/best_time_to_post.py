from __future__ import annotations

from typing import Any


class BestTimeToPostService:
    """Best Time to Post recommendation engine.

    Provides evidence-based or preliminary heuristic publishing time recommendations
    for Facebook, Instagram, TikTok, YouTube, and LinkedIn.
    Never fabricates fake engagement analytics.
    """

    SUPPORTED_PLATFORMS = ["facebook", "instagram", "tiktok", "youtube", "linkedin"]

    # Base heuristic slots (platform-level industry research baselines)
    _PLATFORM_BASE_HEURISTICS: dict[str, list[dict[str, str]]] = {
        "facebook": [
            {"day": "wednesday", "time": "09:00", "slot_type": "mid_morning"},
            {"day": "thursday", "time": "11:30", "slot_type": "mid_day"},
            {"day": "friday", "time": "13:00", "slot_type": "early_afternoon"},
        ],
        "instagram": [
            {"day": "monday", "time": "11:30", "slot_type": "lunch_break"},
            {"day": "wednesday", "time": "18:30", "slot_type": "evening_relaxation"},
            {"day": "friday", "time": "20:00", "slot_type": "prime_evening"},
        ],
        "tiktok": [
            {"day": "tuesday", "time": "16:00", "slot_type": "afternoon_break"},
            {"day": "thursday", "time": "19:30", "slot_type": "prime_entertainment"},
            {"day": "saturday", "time": "21:00", "slot_type": "weekend_night"},
        ],
        "youtube": [
            {"day": "thursday", "time": "14:30", "slot_type": "pre_prime_index"},
            {"day": "friday", "time": "16:00", "slot_type": "weekend_kickoff"},
            {"day": "saturday", "time": "11:00", "slot_type": "weekend_morning"},
        ],
        "linkedin": [
            {"day": "tuesday", "time": "08:30", "slot_type": "morning_commute"},
            {"day": "wednesday", "time": "10:15", "slot_type": "focus_break"},
            {"day": "thursday", "time": "12:45", "slot_type": "lunch_reading"},
        ],
    }

    # Brand archetype modifiers
    _ARCHETYPE_MODIFIERS: dict[str, dict[str, Any]] = {
        "discipline_coach": {
            "shift_minutes": -90,  # Earlier morning motivation
            "preferred_slots": ["06:30", "07:30", "19:00", "20:30"],
            "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
            "reason_prefix": "Audience values morning routine adherence and daily discipline check-ins.",
        },
        "fitness": {
            "shift_minutes": -60,
            "preferred_slots": ["07:00", "12:00", "18:00"],
            "days": ["monday", "wednesday", "friday", "saturday"],
            "reason_prefix": "Scheduled around pre-work workout and evening training sessions.",
        },
        "retail": {
            "shift_minutes": 60,  # Later shopping & lunch
            "preferred_slots": ["12:30", "19:30", "21:00"],
            "days": ["thursday", "friday", "saturday", "sunday"],
            "reason_prefix": "Optimized for pay-day windows, weekend shopping intent, and casual browsing.",
        },
        "education": {
            "shift_minutes": 0,
            "preferred_slots": ["09:00", "14:00", "19:00"],
            "days": ["tuesday", "wednesday", "thursday"],
            "reason_prefix": "Scheduled for active learning periods and dedicated mid-week study hours.",
        },
        "corporate": {
            "shift_minutes": 0,
            "preferred_slots": ["08:30", "10:00", "13:30", "15:00"],
            "days": ["tuesday", "wednesday", "thursday"],
            "reason_prefix": "Targeted at B2B professional hours and mid-week executive workflow.",
        },
    }

    @classmethod
    def get_recommendations(
        cls,
        brand_payload: dict[str, Any],
        platform: str | None = None,
        timezone: str | None = None,
        historical_records: list[dict[str, Any]] | None = None,
    ) -> list[dict[str, Any]]:
        """Generate structured best-time-to-post recommendations."""
        target_platforms = (
            [platform.lower().strip()]
            if platform and platform.lower().strip() in cls.SUPPORTED_PLATFORMS
            else cls.SUPPORTED_PLATFORMS
        )

        effective_tz = (
            timezone
            if (timezone and timezone != "UTC")
            else (
                brand_payload.get("timezone")
                or (brand_payload.get("settings") or {}).get("timezone")
                or timezone
                or "UTC"
            )
        )

        category = " ".join([
            str(brand_payload.get("niche") or ""),
            str(brand_payload.get("category") or ""),
            str(brand_payload.get("industry") or ""),
            str(brand_payload.get("name") or ""),
        ]).lower()

        archetype_key = cls._detect_archetype(category)
        archetype_config = cls._ARCHETYPE_MODIFIERS.get(archetype_key)

        recommendations: list[dict[str, Any]] = []

        # Check if genuine historical data is present
        has_real_history = bool(historical_records and len(historical_records) >= 5)

        for plat in target_platforms:
            base_slots = cls._PLATFORM_BASE_HEURISTICS.get(plat, [])

            if has_real_history:
                # Build recommendations from actual engagement records
                plat_recs = cls._build_historical_recommendations(
                    plat=plat,
                    historical_records=historical_records or [],
                    tz=effective_tz,
                    brand_name=brand_payload.get("name", "Brand"),
                )
                if plat_recs:
                    recommendations.extend(plat_recs)
                    continue

            # Preliminary heuristic recommendation tailored to brand
            for slot in base_slots:
                day = slot["day"]
                time = slot["time"]

                # Adjust time if archetype provides specific preferred slots
                if archetype_config and archetype_config.get("preferred_slots"):
                    # Use tailored slot if available
                    custom_slots = archetype_config["preferred_slots"]
                    idx = base_slots.index(slot) % len(custom_slots)
                    time = custom_slots[idx]

                reason = (
                    f"{archetype_config['reason_prefix']} Baseline for {plat.capitalize()} indicates strong activity on {day.capitalize()} at {time}."
                    if archetype_config
                    else f"Preliminary heuristic recommendation for {plat.capitalize()} on {day.capitalize()} at {time}. No historical engagement recorded yet."
                )

                recommendations.append({
                    "platform": plat,
                    "day_of_week": day,
                    "suggested_time": time,
                    "timezone": effective_tz,
                    "reason": reason,
                    "confidence": 0.75 if archetype_config else 0.65,
                    "evidence_type": "brand_profile_aligned" if archetype_config else "heuristic_preliminary",
                    "data_basis": "Configurable scheduling heuristics aligned with brand category and platform engagement curves.",
                    "is_preliminary": True,
                })

        return recommendations

    @classmethod
    def _detect_archetype(cls, category_str: str) -> str:
        cat = category_str.lower()
        if any(w in cat for w in ["discipline", "mindset", "stoic", "habits"]):
            return "discipline_coach"
        if any(w in cat for w in ["fitness", "gym", "workout", "sports", "athlete"]):
            return "fitness"
        if any(w in cat for w in ["retail", "ecommerce", "store", "product", "shop", "fashion"]):
            return "retail"
        if any(w in cat for w in ["education", "course", "learning", "training", "academic"]):
            return "education"
        if any(w in cat for w in ["saas", "b2b", "corporate", "tech", "finance", "business"]):
            return "corporate"
        return "general"

    @classmethod
    def _build_historical_recommendations(
        cls,
        plat: str,
        historical_records: list[dict[str, Any]],
        tz: str,
        brand_name: str,
    ) -> list[dict[str, Any]]:
        """Extract top publishing slots from genuine historical performance."""
        plat_records = [
            r for r in historical_records
            if str(r.get("platform", "")).lower() == plat and r.get("engagement_score") is not None
        ]
        if not plat_records:
            return []

        # Sort by genuine engagement
        plat_records.sort(key=lambda x: float(x.get("engagement_score", 0)), reverse=True)
        top_records = plat_records[:3]

        recs = []
        for r in top_records:
            day = str(r.get("day_of_week", "wednesday")).lower()
            time = str(r.get("time", "12:00"))
            score = float(r.get("engagement_score", 0))

            recs.append({
                "platform": plat,
                "day_of_week": day,
                "suggested_time": time,
                "timezone": tz,
                "reason": f"Based on historical data for {brand_name}: past posts at this slot achieved higher engagement score ({score:.1f}).",
                "confidence": min(0.95, 0.75 + (score / 500)),
                "evidence_type": "historical_performance",
                "data_basis": f"Evaluated from {len(plat_records)} historical post records.",
                "is_preliminary": False,
            })
        return recs
