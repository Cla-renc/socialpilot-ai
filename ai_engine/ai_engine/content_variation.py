from __future__ import annotations

from typing import Any
from .brand_context import BrandProfile
from .component_generators import HookGenerator, CallToActionGenerator, HashtagGenerator
from .platform_adaptation import PlatformAdaptationService
from .content_quality import ContentQualityEvaluator


class ContentVariationGenerator:
    STYLES = [
        ("professional", "Professional & Authoritative", "Delivers insights through a strategic, analytical lens with executive clarity."),
        ("conversational", "Conversational & Relatable", "Approachable, warm, and authentic, speaking as a trusted peer."),
        ("bold", "Bold & Thought-Provoking", "Challenges industry status quo with a contrarian, direct viewpoint."),
        ("educational", "Educational & Actionable", "Breaks down concepts into step-by-step frameworks and lessons."),
        ("direct", "Short & High-Impact", "Cuts through the noise with minimalist, punchy delivery."),
    ]

    @classmethod
    def generate_variations(
        cls,
        base_title: str,
        base_body: str,
        topic: str,
        platform: str,
        brand: BrandProfile,
        count: int = 3,
        trend: dict[str, Any] | None = None,
    ) -> list[dict[str, Any]]:
        variations: list[dict[str, Any]] = []
        effective_count = max(2, min(count, len(cls.STYLES)))

        for i in range(effective_count):
            style_key, style_label, style_desc = cls.STYLES[i]

            hook = HookGenerator.generate(topic, platform, brand, style=style_key)
            cta = CallToActionGenerator.generate("engagement", platform, brand)
            hashtags = HashtagGenerator.generate(brand, topic, platform, count=4)

            # Generate distinct body based on variation style
            if style_key == "professional":
                body = (
                    f"In today's landscape, effectively addressing {topic} requires deliberate systems.\n\n"
                    f"At {brand.name}, our operational focus centers on three principles:\n"
                    f"1. Strategic alignment with core objectives.\n"
                    f"2. Consistent execution over intermittent motivation.\n"
                    f"3. Measurable indicators to evaluate progress."
                )
            elif style_key == "conversational":
                body = (
                    f"Let's be completely honest about {topic}.\n\n"
                    f"We often make it way more complicated than it needs to be.\n"
                    f"The secret isn't a complex hack. It's showing up consistently and doing the simple things right, every single day.\n\n"
                    f"That is the foundation {brand.name} was built upon."
                )
            elif style_key == "bold":
                body = (
                    f"Most people are doing {topic} completely backwards.\n\n"
                    f"They chase easy shortcuts and wonder why results don't last.\n"
                    f"If you want real progress in {brand.niche}, raise your baseline standards and stop accepting mediocre execution."
                )
            elif style_key == "educational":
                body = (
                    f"Here is a simple 3-step framework for mastering {topic}:\n\n"
                    f"Step 1: Audit your current friction points.\n"
                    f"Step 2: Establish a repeatable daily habit.\n"
                    f"Step 3: Track outputs weekly to ensure momentum.\n\n"
                    f"Save this for your next planning session."
                )
            else:  # direct
                body = (
                    f"{topic} simplified:\n\n"
                    f"• Focus on what you control.\n"
                    f"• Eliminate unnecessary friction.\n"
                    f"• Repeat daily.\n\n"
                    f"Results take care of themselves."
                )

            # Adapt for the target platform
            adapted = PlatformAdaptationService.format_content_for_platform(
                platform=platform,
                title=f"{base_title} ({style_label})",
                hook=hook,
                body=body,
                caption=f"{hook} {cta}",
                call_to_action=cta,
                hashtags=hashtags,
            )

            # Quality evaluation
            eval_result = ContentQualityEvaluator.evaluate(
                title=adapted["title"],
                hook=adapted["hook"],
                body=adapted["body"],
                caption=adapted["caption"],
                call_to_action=adapted["call_to_action"],
                hashtags=adapted["hashtags"],
                platform=platform,
                brand=brand,
                trend=trend,
            )

            variations.append({
                "style": style_key,
                "style_label": style_label,
                "style_description": style_desc,
                "title": adapted["title"],
                "hook": adapted["hook"],
                "body": adapted["body"],
                "caption": adapted["caption"],
                "call_to_action": adapted["call_to_action"],
                "hashtags": adapted["hashtags"],
                "quality_score": eval_result["quality_score"],
                "quality_evaluation": eval_result,
                "is_selected": False,
            })

        return variations
