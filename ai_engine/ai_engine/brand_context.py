from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any


def _clean_str_list(value: Any) -> list[str]:
    if isinstance(value, str):
        parts = [p.strip() for p in value.replace("\n", ",").split(",") if p.strip()]
        return parts
    if isinstance(value, list):
        return [str(p).strip() for p in value if str(p).strip()]
    return []


@dataclass(frozen=True)
class BrandProfile:
    id: int | None
    name: str
    industry: str
    niche: str
    description: str
    audience: str
    audience_interests: tuple[str, ...]
    region: str
    language: str
    tone: str
    voice: str
    personality: str
    categories: tuple[str, ...]
    objectives: tuple[str, ...]
    writing_style: str
    keywords: tuple[str, ...]
    hashtag_preferences: tuple[str, ...]
    topics_to_avoid: tuple[str, ...]
    connected_platforms: tuple[str, ...]
    missing_fields: tuple[str, ...] = field(default_factory=tuple)

    @property
    def archetype(self) -> str:
        """Determines writing personality archetype to prevent generic copy."""
        ind = self.industry.lower()
        niche = self.niche.lower()
        tone = (self.tone + " " + self.personality).lower()

        if any(w in ind or w in niche or w in tone for w in ["fitness", "discipline", "personal development", "mindset", "coaching", "stoic", "habits"]):
            return "discipline_coach"
        if any(w in ind or w in niche for w in ["ecommerce", "retail", "fashion", "beauty", "products", "shop", "apparel", "store"]):
            return "commercial_retail"
        if any(w in ind or w in niche for w in ["saas", "software", "tech", "ai", "b2b", "developer", "fintech", "startup"]):
            return "tech_authority"
        if any(w in ind or w in niche for w in ["healthcare", "wellness", "medical", "nutrition", "therapy"]):
            return "wellness_guide"
        if any(w in ind or w in niche for w in ["finance", "investing", "crypto", "real estate", "accounting"]):
            return "financial_strategist"
        return "authentic_creator"


class BrandContextService:
    @staticmethod
    def parse(payload: dict[str, Any]) -> BrandProfile:
        settings = payload.get("settings") or {}
        missing: list[str] = []

        name = str(payload.get("name") or "Brand").strip()
        industry = str(payload.get("industry") or "").strip()
        if not industry:
            missing.append("industry")
            industry = "General Business"

        niche = str(payload.get("niche") or "").strip()
        if not niche:
            missing.append("niche")
            niche = industry

        description = str(payload.get("description") or "").strip()
        audience = str(payload.get("audience") or "").strip()
        if not audience:
            missing.append("audience")
            audience = "Engaged Community & Potential Clients"

        audience_interests = tuple(_clean_str_list(payload.get("audience_interests")))
        region = str(payload.get("region") or "Global").strip()
        language = str(payload.get("language") or "en").strip()

        tone = str(payload.get("tone") or "").strip()
        voice = str(settings.get("brand_voice") or "").strip()
        if not tone and not voice:
            missing.append("tone")
            tone = "confident and practical"
            voice = tone
        elif not tone:
            tone = voice
        elif not voice:
            voice = tone

        personality = str(payload.get("personality") or voice).strip()

        categories = tuple(_clean_str_list(settings.get("content_formats") or settings.get("content_preferences")))
        objectives = tuple(_clean_str_list(settings.get("marketing_objectives")))
        writing_style = str(settings.get("writing_style") or "clear, direct, and actionable").strip()
        keywords = tuple(_clean_str_list(settings.get("brand_keywords")))

        hashtags = tuple(_clean_str_list(settings.get("hashtag_preferences") or payload.get("hashtag_preferences")))
        avoid = tuple(_clean_str_list(settings.get("restrictions") or settings.get("topics_to_avoid") or payload.get("topics_to_avoid")))
        platforms = tuple(_clean_str_list(payload.get("connected_platforms")))

        return BrandProfile(
            id=payload.get("id"),
            name=name,
            industry=industry,
            niche=niche,
            description=description,
            audience=audience,
            audience_interests=audience_interests,
            region=region,
            language=language,
            tone=tone,
            voice=voice,
            personality=personality,
            categories=categories,
            objectives=objectives,
            writing_style=writing_style,
            keywords=keywords,
            hashtag_preferences=hashtags,
            topics_to_avoid=avoid,
            connected_platforms=platforms,
            missing_fields=tuple(missing),
        )

    @staticmethod
    def get_style_directives(profile: BrandProfile) -> str:
        """Returns specific prompt directives for the archetype."""
        arch = profile.archetype
        if arch == "discipline_coach":
            return (
                f"You write for {profile.name}, a discipline and high-performance brand. "
                "Voice is direct, uncompromising, encouraging yet grounded in accountability. "
                "Do NOT use fluffy corporate jargon, fake hype, or salesy spam. "
                "Focus on daily consistency, mindset, standards, and practical execution."
            )
        if arch == "commercial_retail":
            return (
                f"You write for {profile.name}, a lifestyle & commercial brand. "
                "Voice is vibrant, welcoming, benefit-driven, and visually evocative. "
                "Highlight product utility, sensory details, and tangible customer value without sounding desperate."
            )
        if arch == "tech_authority":
            return (
                f"You write for {profile.name}, a modern tech and software organization. "
                "Voice is crisp, insightful, authoritative, and data-informed. "
                "Focus on problem-solving, efficiency, engineering elegance, and industry trends."
            )
        if arch == "wellness_guide":
            return (
                f"You write for {profile.name}, an authentic wellness and health brand. "
                "Voice is empathetic, scientifically grounded, supportive, and restorative. "
                "Focus on holistic well-being, sustainable habits, and mindful progress."
            )
        if arch == "financial_strategist":
            return (
                f"You write for {profile.name}, a financial and strategic insight brand. "
                "Voice is measured, analytical, trustworthy, and actionable. "
                "Focus on risk management, long-term wealth, economic reality, and clarity."
            )
        return (
            f"You write for {profile.name}. Tone is {profile.tone}. "
            f"Writing style: {profile.writing_style}. Speak directly to {profile.audience}."
        )
