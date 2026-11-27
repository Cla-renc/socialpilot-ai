from __future__ import annotations

import re
from dataclasses import asdict, dataclass, field
from typing import Any

from .context import BrandContext
from .normalization import extract_content_tokens
from .scoring import analyze_relevance


@dataclass
class ContentPackage:
    recommended_angle: str
    alternative_hooks: list[str]
    draft_caption: str
    suggested_format: str
    call_to_action: str
    hashtags: list[str]
    platform_concepts: dict[str, dict[str, Any]]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass
class BrandRecommendation:
    topic_title: str
    cluster_id: str | None
    score: int
    relevance_classification: str  # 'direct', 'adjacent', 'weak'
    explanation: dict[str, str]
    content_package: dict[str, Any]
    target_platform: str
    suggested_format: str
    status: str = "generated"

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class BrandRelevanceEngine:
    """Evaluates candidate trend relevance against brand identity and generates explainable scores."""

    DIRECT_THRESHOLD = 75
    ADJACENT_THRESHOLD = 45
    MINIMUM_THRESHOLD = 25

    def evaluate(
        self,
        context: BrandContext,
        title: str,
        summary: str,
        sources: list[dict[str, Any]],
        opportunity_score: int,
        is_cross_platform: bool = False,
        historical_titles: tuple[str, ...] | None = None,
        published_at: Any | None = None,
        geographic_market: str | None = None,
    ) -> tuple[int, str, dict[str, str]]:
        """Compute brand relevance score, classification, and explainable rationale."""
        # 1. Base semantic relevance using core domain & keyword analyzer
        base_score, base_reason = analyze_relevance(
            context=context,
            title=title,
            description=summary,
            published_at=published_at,
            geographic_market=geographic_market or context.region,
        )

        # 2. Add brand-specific precision adjustments
        corpus = f"{title} {summary}".casefold()
        title_lower = title.casefold()
        precision_bonus = 0

        # Exact niche match in title or summary (handles compound niches with &, /, or commas)
        if context.niche:
            niche_clean = context.niche.casefold()
            niche_parts = [p.strip().casefold() for p in re.split(r"[&/,]", context.niche) if len(p.strip()) >= 3]
            if niche_clean in title_lower or any(p in title_lower for p in niche_parts):
                precision_bonus += 15
            elif niche_clean in corpus or any(p in corpus for p in niche_parts):
                precision_bonus += 8

        # Keyword match in title
        if any(kw.casefold() in title_lower for kw in context.keywords):
            precision_bonus += 10

        # Audience interest match
        if any(ai.casefold() in corpus for ai in context.audience_interests):
            precision_bonus += 8

        # 3. Multi-source and cross-platform verification boost (up to 15 pts)
        source_count = len(sources)
        verification_boost = 0
        if source_count > 1:
            verification_boost += min(10, (source_count - 1) * 3)
        if is_cross_platform:
            verification_boost += 5

        # Total score calculation
        if base_score >= 15:
            final_score = base_score + precision_bonus + verification_boost
        else:
            final_score = base_score

        final_score = max(0, min(100, final_score))

        # 3. Four-tier classification
        if final_score >= self.DIRECT_THRESHOLD:
            classification = "direct"
        elif final_score >= self.ADJACENT_THRESHOLD:
            classification = "adjacent"
        elif final_score >= self.MINIMUM_THRESHOLD:
            classification = "weak"
        else:
            classification = "irrelevant"

        # 4. Explainable Breakdown
        brand_name = context.name or "the brand"
        niche_label = context.niche or context.industry or "your industry"
        audience_label = context.audience or "target audience"

        why_it_matters = (
            f"Directly impacts {niche_label} discussions: {base_reason}."
            if classification == "direct"
            else (
                f"Adjacent trend that {brand_name} can adapt to bridge {niche_label} solutions into wider interest ({base_reason})."
                if classification == "adjacent"
                else f"Tangential topic with limited direct overlap to {niche_label} ({base_reason})."
            )
        )

        audience_interest = (
            f"Resonates with {audience_label} looking for timely insights, practical applications, and industry updates."
            if context.audience_interests
            else f"Relevant to {audience_label} engaged in trending conversations."
        )

        timeliness = (
            f"Active across multiple platforms ({source_count} sources) showing expanding cross-channel discussion."
            if is_cross_platform
            else f"Documented in {source_count} verified source publication(s) with opportunity score of {opportunity_score}."
        )

        explanation = {
            "why_it_matters": why_it_matters,
            "audience_interest": audience_interest,
            "timeliness_and_evidence": timeliness,
        }

        return final_score, classification, explanation


class ContentRecommendationGenerator:
    """Generates brand-specific content direction, hooks, full captions, and multi-platform adaptations."""

    FORMATS = ["short_video", "carousel", "text_thread", "infographic", "story"]

    def __init__(self, angle_generator: Any | None = None) -> None:
        self.angle_generator = angle_generator

    def generate(
        self,
        context: BrandContext,
        title: str,
        summary: str,
        classification: str,
        sources: list[dict[str, Any]],
        suggested_platform: str = "cross_platform",
    ) -> ContentPackage:
        """Create a complete, brand-tailored content recommendation package."""
        brand_name = context.name or "Our Brand"
        niche = context.niche or context.industry or "industry"
        tone = (context.tone or context.brand_voice or "professional").casefold()
        audience = context.audience or "creators and professionals"

        # Determine best format
        suggested_format = self._select_format(context)

        # 1. Recommended Editorial Angle
        angle = self._generate_angle(title, brand_name, niche, classification, tone)

        # 2. Alternative Hooks (exactly 3 distinct hooks)
        hooks = self._generate_hooks(title, niche, audience, tone)

        # 3. Draft Caption / Script
        draft_caption = self._generate_caption(title, summary, angle, brand_name, niche, tone, context)

        # 4. Call to Action
        cta = self._generate_cta(context, tone)

        # 5. Hashtags (5-8 hashtags)
        hashtags = self._generate_hashtags(title, context)

        # 6. Multi-Platform Concepts
        platform_concepts = self._generate_platform_concepts(title, angle, niche, tone, audience)

        return ContentPackage(
            recommended_angle=angle,
            alternative_hooks=hooks,
            draft_caption=draft_caption,
            suggested_format=suggested_format,
            call_to_action=cta,
            hashtags=hashtags,
            platform_concepts=platform_concepts,
        )

    def _select_format(self, context: BrandContext) -> str:
        preferred = [fmt.casefold() for fmt in context.content_categories]
        for fmt in ["short_video", "carousel", "text_thread", "infographic", "story"]:
            for pref in preferred:
                if fmt in pref or pref in fmt:
                    return fmt
        return "carousel" if "educational" in preferred else "short_video"

    def _generate_angle(
        self, title: str, brand_name: str, niche: str, classification: str, tone: str
    ) -> str:
        clean_title = title.rstrip(".:?!")
        if classification == "direct":
            return f"How {clean_title} is directly transforming {niche}, and what {brand_name}'s perspective means for your workflow."
        if classification == "adjacent":
            return f"Taking lessons from the {clean_title} trend and applying them pragmatically inside {niche}."
        return f"A quick breakdown of {clean_title} and what it signals for forward-thinking {niche} teams."

    def _generate_hooks(self, title: str, niche: str, audience: str, tone: str) -> list[str]:
        clean = title.rstrip(".:?!")
        if "casual" in tone or "humorous" in tone:
            return [
                f"Everyone is talking about {clean}, but here is the part everyone missed.",
                f"Is {clean} actually worth your time in {niche}? Let's settle this.",
                f"If you're in {niche}, stop scrolling: {clean} just changed the game.",
            ]
        if "authoritative" in tone or "professional" in tone:
            return [
                f"Why {clean} represents a pivotal shift for {niche} leaders this quarter.",
                f"3 strategic implications of {clean} that every {audience} should prepare for.",
                f"Beyond the hype: what {clean} actually delivers for {niche} performance.",
            ]
        # Default / inspirational / balanced
        return [
            f"What does {clean} really mean for the future of {niche}?",
            f"How forward-thinking {audience} are already capitalizing on {clean}.",
            f"The essential breakdown: 3 key takeaways from {clean} you can apply today.",
        ]

    def _generate_caption(
        self,
        title: str,
        summary: str,
        angle: str,
        brand_name: str,
        niche: str,
        tone: str,
        context: BrandContext,
    ) -> str:
        clean = title.rstrip(".:?!")
        first_line = f"The conversation around **{clean}** is accelerating."
        explanation = (
            f"Here is why this matters: {angle}\n\n"
            f"Key observations:\n"
            f"• Market Shift: How this impacts everyday practices in {niche}.\n"
            f"• Immediate Opportunity: Where proactive teams are finding leverage right now.\n"
            f"• Long-term Outlook: What to watch over the coming weeks as this develops."
        )
        closing = f"\n\nAt {brand_name}, we are continuously analyzing how these developments shape {niche}."
        return f"{first_line}\n\n{explanation}{closing}"

    def _generate_cta(self, context: BrandContext, tone: str) -> str:
        if context.objectives:
            obj = context.objectives[0].casefold()
            if "lead" in obj or "conversion" in obj:
                return "Download our actionable playbook or save this post for your team's next sprint."
            if "engagement" in obj or "community" in obj:
                return "What's your take on this trend? Share your perspective in the comments below."
        return "Save this post to reference later, and share it with someone navigating these changes."

    def _generate_hashtags(self, title: str, context: BrandContext) -> list[str]:
        tags: list[str] = []

        # From title words
        title_tokens = extract_content_tokens(title)
        for t in list(title_tokens)[:3]:
            if len(t) >= 3 and not t.isdigit():
                tags.append(f"#{t.lower()}")

        # From brand niche/keywords
        if context.niche:
            clean_niche = re.sub(r"[^a-zA-Z0-9]", "", context.niche)
            if clean_niche:
                tags.append(f"#{clean_niche.lower()}")

        for kw in context.keywords[:3]:
            clean_kw = re.sub(r"[^a-zA-Z0-9]", "", kw)
            if clean_kw and f"#{clean_kw.lower()}" not in tags:
                tags.append(f"#{clean_kw.lower()}")

        # Platform/General trend tags to ensure 5-8 tags
        general_pool = ["#TrendIntelligence", "#ContentStrategy", "#Innovation", "#Growth"]
        for g in general_pool:
            if len(tags) >= 8:
                break
            if g.lower() not in [t.lower() for t in tags]:
                tags.append(g)

        # Guarantee at least 5
        while len(tags) < 5:
            tags.append(f"#Update{len(tags)}")

        return tags[:8]

    def _generate_platform_concepts(
        self, title: str, angle: str, niche: str, tone: str, audience: str
    ) -> dict[str, dict[str, Any]]:
        clean = title.rstrip(".:?!")
        return {
            "tiktok": {
                "hook": f"Wait, did you see what just happened with {clean}?",
                "visual_concept": "Fast-paced talking head with on-screen headline screenshot and green-screen background.",
                "duration": "30-45s",
                "sound_type": "Subtle trending lo-fi or conversational ambient background.",
            },
            "instagram": {
                "format": "Carousel (7 slides) or Reel",
                "slide_1_hook": f"Why {clean} matters for {niche} (and what to do about it)",
                "slide_progression": [
                    "Slide 1: Hook and context",
                    "Slide 2-3: The hidden problem",
                    "Slide 4-5: 3 action steps",
                    "Slide 6: Brand perspective",
                    "Slide 7: Save & share CTA",
                ],
                "caption_tone": tone,
            },
            "youtube": {
                "title_concept": f"{clean}: What Every {niche.title()} Needs to Know",
                "format": "Shorts or 6-8 minute deep dive",
                "thumbnail_text": f"{clean.upper()} EXPLAINED",
                "hook_first_5s": f"In the next 60 seconds, here is why {clean} changes everything for {audience}.",
            },
            "facebook": {
                "post_type": "Community Discussion Post with Link or Image",
                "starter_question": f"We're seeing significant discussion around {clean}. How is your team handling this shift?",
                "story_angle": f"A behind-the-scenes look at how {niche} professionals are responding to {clean}.",
            },
            "x": {
                "format": "Single post or 4-tweet thread",
                "tweet_1": f"{clean} is gaining serious momentum. Here are the 3 things that actually matter for {niche}: 🧵👇",
                "tweet_thread_summary": "1/ Context -> 2/ Opportunity -> 3/ Cautionary point -> 4/ Action checklist.",
            },
            "reddit": {
                "suggested_subreddits": [f"r/{niche.replace(' ', '')}", "r/business", "r/marketing"],
                "post_style": "High-value text discussion (No self-promotion)",
                "headline": f"Analysis: The real implications of {clean} for {niche}",
                "body_structure": "Objective breakdown of the data, practical pros/cons, asking community for their ground-level experience.",
            },
        }


class TrendRecommendationEngine:
    """Unified engine combining relevance scoring and content package generation."""

    def __init__(self, angle_generator: Any | None = None) -> None:
        self.relevance_engine = BrandRelevanceEngine()
        self.recommendation_generator = ContentRecommendationGenerator(angle_generator=angle_generator)

    def evaluate_and_recommend(
        self,
        context: BrandContext,
        topic: dict[str, Any],
        historical_topics: list[dict[str, Any]] | None = None,
    ) -> BrandRecommendation | None:
        """Evaluate a topic/cluster and return a BrandRecommendation if above minimum relevance threshold."""
        title = topic.get("title") or ""
        summary = topic.get("summary") or topic.get("description") or ""
        sources = topic.get("sources") or []
        opportunity = topic.get("opportunity_score") or 50
        is_cross_platform = bool(topic.get("is_cross_platform"))
        cluster_id = topic.get("cluster_id")
        published_at = topic.get("published_at")
        geographic_market = topic.get("geographic_market")

        score, classification, explanation = self.relevance_engine.evaluate(
            context=context,
            title=title,
            summary=summary,
            sources=sources,
            opportunity_score=opportunity,
            is_cross_platform=is_cross_platform,
            published_at=published_at,
            geographic_market=geographic_market,
        )

        # Discard irrelevant topics (< 25)
        if classification == "irrelevant":
            return None

        # Determine target platform based on cluster participating platforms
        participating = topic.get("participating_platforms") or []
        target_platform = (
            "cross_platform"
            if len(participating) > 1 or is_cross_platform
            else (participating[0] if participating else "cross_platform")
        )

        content_package = self.recommendation_generator.generate(
            context=context,
            title=title,
            summary=summary,
            classification=classification,
            sources=sources,
            suggested_platform=target_platform,
        )

        return BrandRecommendation(
            topic_title=title,
            cluster_id=cluster_id,
            score=score,
            relevance_classification=classification,
            explanation=explanation,
            content_package=content_package.to_dict(),
            target_platform=target_platform,
            suggested_format=content_package.suggested_format,
            status="generated",
        )
