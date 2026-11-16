from __future__ import annotations

import re
from typing import Any
from .brand_context import BrandProfile


class HookGenerator:
    @staticmethod
    def generate(
        topic: str,
        platform: str,
        brand: BrandProfile,
        style: str = "curiosity",
    ) -> str:
        platform_norm = platform.lower()
        arch = brand.archetype
        style_norm = style.lower()

        if style_norm == "bold":
            return f"Why conventional wisdom regarding {topic} is failing people in 2026:"
        if style_norm == "conversational":
            return f"Let's have an honest conversation about {topic} for a minute:"
        if style_norm == "educational":
            return f"A practical, step-by-step breakdown of how to solve {topic}:"
        if style_norm == "direct":
            return f"{topic} in 3 actionable points:"
        if style_norm == "professional":
            return f"Strategic analysis: The critical factors governing success with {topic}:"

        if platform_norm == "tiktok":
            hooks = [
                f"Stop doing this with {topic} if you actually want results.",
                f"Nobody is talking about this side of {topic}, but they should be.",
                f"The 1 rule about {topic} that changed everything for me:",
            ]
        elif platform_norm == "linkedin":
            hooks = [
                f"Most professionals approach {topic} backwards. Here is what actually works:",
                f"After analyzing {topic} across our industry, one hard truth became clear:",
                f"The strategic reality of {topic} that leadership teams need to know:",
            ]
        elif platform_norm == "instagram":
            hooks = [
                f"Save this before you plan your next move with {topic} 📌",
                f"The unspoken truth about {topic} nobody wants to admit:",
                f"3 things I wish I knew about {topic} when starting out:",
            ]
        elif arch == "discipline_coach":
            hooks = [
                f"You don't lack motivation with {topic}. You lack ruthless systems.",
                f"The uncomfortable truth about {topic} you are avoiding:",
                f"Standards over feelings: why {topic} separates the top 1% from the rest.",
            ]
        else:
            hooks = [
                f"Here is what happens when you treat {topic} as a non-negotiable:",
                f"A practical breakdown of {topic} that actually moves the needle:",
                f"The single most effective way to solve {topic} this week:",
            ]

        idx = abs(hash(topic + style)) % len(hooks)
        return hooks[idx]


class CallToActionGenerator:
    @staticmethod
    def generate(
        objective: str,
        platform: str,
        brand: BrandProfile,
    ) -> str:
        p = platform.lower()
        obj = objective.lower()

        if obj in {"community_building", "engagement"}:
            if p == "linkedin":
                return "Drop your thoughts below: how are you approaching this in your workflow?"
            if p == "instagram":
                return "Which of these resonates most? Share your takeaway in the comments! 👇"
            if p == "tiktok":
                return "Have you tried this yet? Let me know in the comments."
            return "What has been your experience with this? Let's discuss in the comments."

        if obj in {"education", "awareness"}:
            if p == "instagram":
                return f"Save this post so you have it ready when tackling your next milestone with {brand.name}."
            if p == "linkedin":
                return "Repost this if you found it useful for your network."
            return f"Follow {brand.name} for more daily insights and practical strategies."

        if obj in {"product_promotion", "lead_generation", "website_traffic"}:
            if p == "instagram":
                return f"Tap the link in our bio to explore how {brand.name} can help you achieve this faster."
            if p == "linkedin":
                return f"Ready to take this further? Explore our full framework at the link in the comments or send us a message."
            return f"Visit {brand.name} to see the complete guide and get started today."

        return f"Share this with someone who needs to hear it today."


class CaptionGenerator:
    @staticmethod
    def generate(
        title: str,
        hook: str,
        platform: str,
        brand: BrandProfile,
    ) -> str:
        p = platform.lower()
        if p == "youtube":
            return f"In this video, we break down {title} and share actionable steps to help {brand.audience} get consistent results."
        if p == "instagram":
            return f"{hook}\n\nSwipe through for the complete breakdown or save for later reference."
        return f"{hook} Here is our actionable perspective from {brand.name}."


class HashtagGenerator:
    @staticmethod
    def generate(
        brand: BrandProfile,
        topic: str,
        platform: str,
        count: int = 5,
    ) -> list[str]:
        # Clean topic terms
        topic_words = [re.sub(r'[^a-zA-Z0-9]', '', w).capitalize() for w in topic.split() if len(w) > 3][:3]
        niche_tag = re.sub(r'[^a-zA-Z0-9]', '', brand.niche).capitalize()
        industry_tag = re.sub(r'[^a-zA-Z0-9]', '', brand.industry).capitalize()
        brand_tag = re.sub(r'[^a-zA-Z0-9]', '', brand.name).capitalize()

        candidates = list(brand.hashtag_preferences)
        for w in topic_words:
            if w:
                candidates.append(w)
        if niche_tag:
            candidates.append(niche_tag)
        if industry_tag:
            candidates.append(industry_tag)
        if brand_tag:
            candidates.append(brand_tag)

        # Remove duplicates preserving order
        unique_tags = []
        for tag in candidates:
            clean = tag.lstrip("#").strip()
            if clean and clean.lower() not in [t.lower() for t in unique_tags]:
                # Check topics to avoid
                if not any(avoid.lower() in clean.lower() for avoid in brand.topics_to_avoid):
                    unique_tags.append(clean)

        return [f"#{t}" for t in unique_tags[:count]]
