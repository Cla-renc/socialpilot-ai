from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any
from .brand_context import BrandProfile


@dataclass(frozen=True)
class ContentStrategy:
    topic: str
    objective: str
    target_audience: str
    category: str
    recommended_angle: str
    main_message: str
    suggested_format: str
    reason: str
    trend_references: list[dict[str, Any]] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "topic": self.topic,
            "objective": self.objective,
            "target_audience": self.target_audience,
            "category": self.category,
            "recommended_angle": self.recommended_angle,
            "main_message": self.main_message,
            "suggested_format": self.suggested_format,
            "reason": self.reason,
            "trend_references": self.trend_references,
        }


class ContentStrategyService:
    OBJECTIVES = {
        "awareness": "Increase brand reach and introduce core philosophy to a wider relevant audience.",
        "engagement": "Spark active discussions, comments, and community interactions around a meaningful question.",
        "education": "Break down a complex problem into actionable steps, tutorials, or framework takeaways.",
        "community_building": "Celebrate shared values, foster peer connection, and deepen loyalty with current followers.",
        "brand_authority": "Establish domain mastery through contrarian insights, case studies, or deep industry analysis.",
        "product_promotion": "Highlight specific product benefits and tangible user outcomes with a clear direct call to action.",
        "website_traffic": "Provide an intriguing hook and high-value premise that directs readers to the main site/article.",
        "lead_generation": "Deliver high-intent value that encourages inquiries, signups, or direct messages.",
    }

    FORMATS = {
        "linkedin": "thought_leadership",
        "twitter": "insight_thread",
        "instagram": "visual_story_caption",
        "tiktok": "short_form_script",
        "youtube": "short_video_narration",
        "facebook": "community_discussion",
    }

    @classmethod
    def develop_strategy(
        cls,
        brand: BrandProfile,
        objective: str | None = None,
        category: str | None = None,
        platform: str | None = None,
        trend: dict[str, Any] | None = None,
        instructions: str | None = None,
    ) -> ContentStrategy:
        platform_norm = (platform or "linkedin").lower()
        obj_key = (objective or (brand.objectives[0] if brand.objectives else "engagement")).lower().replace(" ", "_")
        if obj_key not in cls.OBJECTIVES:
            obj_key = "engagement"

        cat = (category or (brand.categories[0] if brand.categories else "educational")).lower()

        # Trend-backed strategy vs Evergreen strategy
        trend_refs: list[dict[str, Any]] = []
        if trend and trend.get("title"):
            topic = trend.get("title", "").strip()
            desc = trend.get("description") or trend.get("summary") or ""
            momentum = trend.get("momentum") or "emerging"
            relevance = trend.get("relevance_score") or 75
            sources = trend.get("sources") or []

            trend_refs.append({
                "id": trend.get("id"),
                "title": topic,
                "momentum": momentum,
                "relevance_score": relevance,
                "source_count": len(sources),
                "sources": [
                    {
                        "source_name": s.get("source_name") or s.get("source_key") or "External Source",
                        "url": s.get("url") or s.get("source_url") or "",
                        "title": s.get("title") or topic,
                    }
                    for s in sources[:3]
                ],
            })

            recommended_angle = f"Analyze the momentum of '{topic}' through the lens of {brand.name}'s focus on {brand.niche}."
            main_message = f"Why {brand.audience} should care about {topic} and how to apply this insight practically."
            reason = f"Leverages observed trend '{topic}' (momentum: {momentum}, relevance: {relevance}) to maximize timeliness while remaining anchored in verified source evidence."
        else:
            # Evergreen / original strategy
            core_keyword = brand.keywords[0] if brand.keywords else brand.niche
            topic = instructions or f"Mastering {core_keyword} for sustainable results"
            recommended_angle = f"Address common misconceptions in {brand.niche} and share {brand.name}'s distinct methodology."
            main_message = f"Real growth in {brand.niche} requires consistent habits, clarity of purpose, and practical systems."
            reason = f"Evergreen strategic foundation designed for {brand.audience} focusing on the core objective of {obj_key}."

        suggested_format = cls.FORMATS.get(platform_norm, "thought_leadership")

        return ContentStrategy(
            topic=topic,
            objective=obj_key,
            target_audience=brand.audience,
            category=cat,
            recommended_angle=recommended_angle,
            main_message=main_message,
            suggested_format=suggested_format,
            reason=reason,
            trend_references=trend_refs,
        )


class ContentIdeaGenerator:
    @staticmethod
    def generate_ideas(brand: BrandProfile, count: int = 5) -> list[dict[str, Any]]:
        ideas = []
        base_topics = list(brand.keywords) + [brand.niche, brand.industry]
        if not base_topics:
            base_topics = ["best practices", "common mistakes", "foundational habits"]

        angles = [
            ("Contrarian Breakdown", "Why conventional wisdom about {topic} is holding you back."),
            ("Practical Framework", "The 3-step system {brand} uses to solve {topic} consistently."),
            ("Lesson Learned / Story", "A real-world breakdown of what happens when you prioritize {topic}."),
            ("Checklist / Guide", "The essential checklist for {audience} navigating {topic}."),
            ("Open Discussion", "What is the single biggest bottleneck you face with {topic}?"),
        ]

        for i in range(min(count, len(angles))):
            topic = base_topics[i % len(base_topics)]
            angle_name, template = angles[i]
            title = template.format(topic=topic, brand=brand.name, audience=brand.audience)
            ideas.append({
                "id": i + 1,
                "title": title,
                "angle": angle_name,
                "topic": topic,
                "category": "educational" if i % 2 == 0 else "engagement",
                "objective": "brand_authority" if i == 0 else "engagement",
            })

        return ideas
