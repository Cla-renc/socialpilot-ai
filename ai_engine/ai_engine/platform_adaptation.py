from __future__ import annotations

from typing import Any
from .brand_context import BrandProfile


class PlatformAdaptationService:
    PLATFORM_SPECS = {
        "linkedin": {
            "name": "LinkedIn",
            "max_characters": 3000,
            "recommended_hook_words": 12,
            "hashtag_count_range": (3, 5),
            "supports_scripts": False,
            "structure_style": "thought_leadership",
            "tone_nuance": "professional, analytical, and actionable",
        },
        "instagram": {
            "name": "Instagram",
            "max_characters": 2200,
            "recommended_hook_words": 8,
            "hashtag_count_range": (5, 12),
            "supports_scripts": True,
            "structure_style": "caption_with_hook",
            "tone_nuance": "visually evocative, personal, and punchy",
        },
        "tiktok": {
            "name": "TikTok",
            "max_characters": 2200,
            "recommended_hook_words": 6,
            "hashtag_count_range": (3, 6),
            "supports_scripts": True,
            "structure_style": "video_script",
            "tone_nuance": "conversational, spoken, fast-paced, and unfiltered",
        },
        "youtube": {
            "name": "YouTube",
            "max_characters": 5000,
            "recommended_hook_words": 10,
            "hashtag_count_range": (3, 5),
            "supports_scripts": True,
            "structure_style": "title_description_script",
            "tone_nuance": "educational, engaging, and search-optimized",
        },
        "facebook": {
            "name": "Facebook",
            "max_characters": 5000,
            "recommended_hook_words": 12,
            "hashtag_count_range": (1, 3),
            "supports_scripts": False,
            "structure_style": "community_story",
            "tone_nuance": "approachable, community-driven, and story-centric",
        },
    }

    @classmethod
    def get_specs(cls, platform: str) -> dict[str, Any]:
        p = platform.lower().strip()
        return cls.PLATFORM_SPECS.get(p, cls.PLATFORM_SPECS["linkedin"])

    @classmethod
    def format_content_for_platform(
        cls,
        platform: str,
        title: str,
        hook: str,
        body: str,
        caption: str,
        call_to_action: str,
        hashtags: list[str],
        content_format: str = "post",
    ) -> dict[str, Any]:
        p = platform.lower().strip()
        specs = cls.get_specs(p)
        min_tags, max_tags = specs["hashtag_count_range"]
        tailored_tags = [f"#{t.lstrip('#')}" for t in hashtags[:max_tags]]

        if p == "tiktok":
            # Short-form video script formatting with visual and audio cues
            script_body = (
                f"[HOOK - 0:00-0:03]\n{hook}\n\n"
                f"[SCENE 1 - Visual: Dynamic on-camera explanation]\n{body}\n\n"
                f"[CALL TO ACTION - 0:30]\n{call_to_action}"
            )
            return {
                "title": title,
                "hook": hook,
                "body": script_body,
                "caption": caption or f"{hook} {call_to_action}",
                "call_to_action": call_to_action,
                "hashtags": tailored_tags,
                "format_notes": "Formatted as a short-form video narration script with timing and visual cues.",
            }

        if p == "youtube":
            # Video title + description + script/outline
            video_title = title[:70]
            description = (
                f"{caption}\n\n"
                f"📌 KEY TAKEAWAYS:\n{body}\n\n"
                f"👉 {call_to_action}\n\n"
                f"{' '.join(tailored_tags)}"
            )
            return {
                "title": video_title,
                "hook": hook,
                "body": description,
                "caption": caption,
                "call_to_action": call_to_action,
                "hashtags": tailored_tags,
                "format_notes": "Optimized for YouTube: concise SEO title under 70 chars with structured description.",
            }

        if p == "instagram":
            # Instagram formatted with spacing, clean hook line, and tagged block
            formatted_body = (
                f"{hook}\n\n"
                f"{body}\n\n"
                f"{call_to_action}\n\n"
                f".\n.\n"
                f"{' '.join(tailored_tags)}"
            )
            return {
                "title": title,
                "hook": hook,
                "body": formatted_body,
                "caption": caption or f"{hook}\n\n{body[:150]}...",
                "call_to_action": call_to_action,
                "hashtags": tailored_tags,
                "format_notes": "Spaced for Instagram mobile viewport with high-converting save/share call to action.",
            }

        if p == "facebook":
            # Facebook community post format
            formatted_body = (
                f"{hook}\n\n"
                f"{body}\n\n"
                f"{call_to_action}\n\n"
                f"{' '.join(tailored_tags)}"
            )
            return {
                "title": title,
                "hook": hook,
                "body": formatted_body,
                "caption": caption or hook,
                "call_to_action": call_to_action,
                "hashtags": tailored_tags,
                "format_notes": "Community discussion layout with organic conversational tone.",
            }

        # Default to LinkedIn
        formatted_body = (
            f"{hook}\n\n"
            f"{body}\n\n"
            f"💡 What's your perspective?\n\n"
            f"{call_to_action}\n\n"
            f"{' '.join(tailored_tags)}"
        )
        return {
            "title": title,
            "hook": hook,
            "body": formatted_body,
            "caption": caption or hook,
            "call_to_action": call_to_action,
            "hashtags": tailored_tags,
            "format_notes": "LinkedIn thought-leadership format with line breaks for mobile readability.",
        }
