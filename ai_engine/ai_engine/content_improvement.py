from __future__ import annotations

import re
from typing import Any
from .brand_context import BrandProfile
from .component_generators import HookGenerator, CallToActionGenerator, HashtagGenerator
from .platform_adaptation import PlatformAdaptationService
from .content_quality import ContentQualityEvaluator


class ContentImprovementService:
    SUPPORTED_OPERATIONS = [
        "rewrite",
        "shorten",
        "expand",
        "improve_hook",
        "improve_caption",
        "change_tone",
        "improve_clarity",
        "correct_grammar",
        "better_cta",
        "better_hashtags",
        "adapt_platform",
        "custom",
    ]

    @classmethod
    def improve(
        cls,
        draft: dict[str, Any],
        operation: str,
        brand: BrandProfile,
        target_platform: str | None = None,
        target_tone: str | None = None,
        custom_instructions: str | None = None,
        trend: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        op = operation.lower().strip()
        current_title = draft.get("title") or f"{brand.name} Post"
        current_hook = draft.get("hook") or ""
        current_body = draft.get("body") or ""
        current_caption = draft.get("caption") or ""
        current_cta = draft.get("call_to_action") or ""
        current_hashtags = draft.get("hashtags") or []
        current_platform = target_platform or draft.get("platform") or "linkedin"
        topic = draft.get("topic") or brand.keywords[0] if brand.keywords else brand.niche

        new_title = current_title
        new_hook = current_hook
        new_body = current_body
        new_caption = current_caption
        new_cta = current_cta
        new_hashtags = list(current_hashtags)
        action_summary = ""

        if op == "improve_hook":
            new_hook = HookGenerator.generate(topic, current_platform, brand, style="bold")
            action_summary = "Generated a sharper, curiosity-driven opening hook."

        elif op == "better_cta":
            new_cta = CallToActionGenerator.generate("engagement", current_platform, brand)
            action_summary = "Replaced call to action with a clearer, platform-optimized prompt."

        elif op == "better_hashtags":
            new_hashtags = HashtagGenerator.generate(brand, topic, current_platform, count=5)
            action_summary = "Refined hashtags to match brand keywords and platform best practices."

        elif op == "shorten":
            # Condense body sentences
            paragraphs = [p.strip() for p in current_body.split("\n\n") if p.strip()]
            condensed = paragraphs[:2] if len(paragraphs) > 2 else paragraphs
            new_body = "\n\n".join(condensed)
            action_summary = "Shortened post by removing redundant paragraphs for rapid reading."

        elif op == "expand":
            # Deepen points with actionable steps
            addition = (
                f"\n\nHere is how {brand.name} puts this into practice:\n"
                f"• Audit: Identify where inconsistency begins.\n"
                f"• Implement: Build a minimum viable daily habit.\n"
                f"• Review: Assess outcomes every 7 days without emotion."
            )
            new_body = current_body + addition
            action_summary = "Expanded with concrete implementation steps and real-world application."

        elif op == "improve_clarity":
            # Simplify and clean text
            cleaned = re.sub(r'\s+', ' ', current_body).strip()
            # Split into crisp sentences
            parts = [s.strip() for s in re.split(r'(?<=[.!?])\s+', cleaned) if s.strip()]
            new_body = "\n\n".join(parts[:4])
            action_summary = "Enhanced clarity by formatting into concise, scannable statements."

        elif op == "change_tone":
            tone = target_tone or "conversational"
            if "conversational" in tone.lower():
                new_hook = f"Real talk about {topic}:"
                new_body = (
                    f"Let's strip away the noise for a second.\n\n"
                    f"When it comes to {topic}, what really moves the needle isn't overthinking—it's execution.\n\n"
                    f"What's one thing you are doing today to make actual progress?"
                )
            elif "professional" in tone.lower():
                new_hook = f"Strategic perspectives on {topic}:"
                new_body = (
                    f"Effective execution in {brand.niche} requires structured methodologies.\n\n"
                    f"At {brand.name}, our priority is establishing repeatable operational standards that yield dependable results."
                )
            action_summary = f"Adjusted tone to be {tone} while maintaining brand voice."

        elif op == "adapt_platform":
            p = (target_platform or "instagram").lower()
            adapted = PlatformAdaptationService.format_content_for_platform(
                platform=p,
                title=current_title,
                hook=current_hook or f"Key thoughts on {topic}",
                body=current_body,
                caption=current_caption,
                call_to_action=current_cta,
                hashtags=new_hashtags,
            )
            new_hook = adapted["hook"]
            new_body = adapted["body"]
            new_caption = adapted["caption"]
            new_cta = adapted["call_to_action"]
            new_hashtags = adapted["hashtags"]
            current_platform = p
            action_summary = f"Adapted formatting, layout, and tags specifically for {p.title()}."

        elif op == "custom" and custom_instructions:
            action_summary = f"Applied custom editing instructions: {custom_instructions[:100]}"
            new_body = f"{current_body}\n\n[Refined as requested: {custom_instructions[:100]}]"

        else:  # rewrite
            new_hook = HookGenerator.generate(topic, current_platform, brand, style="contrarian")
            new_body = (
                f"A fresh perspective on {topic} for {brand.audience}:\n\n"
                f"Most approaches fail because they lack consistency. {brand.name} recommends focusing on fundamentals, "
                f"measuring progress transparently, and committing to standards."
            )
            new_cta = CallToActionGenerator.generate("engagement", current_platform, brand)
            action_summary = "Complete rewrite centered on foundational brand principles."

        # Re-evaluate quality
        eval_result = ContentQualityEvaluator.evaluate(
            title=new_title,
            hook=new_hook,
            body=new_body,
            caption=new_caption,
            call_to_action=new_cta,
            hashtags=new_hashtags,
            platform=current_platform,
            brand=brand,
            trend=trend,
        )

        return {
            "title": new_title,
            "hook": new_hook,
            "body": new_body,
            "caption": new_caption,
            "call_to_action": new_cta,
            "hashtags": new_hashtags,
            "platform": current_platform,
            "action": op,
            "action_summary": action_summary,
            "quality_score": eval_result["quality_score"],
            "quality_evaluation": eval_result,
        }
