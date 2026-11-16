from __future__ import annotations

from typing import Any
from .brand_context import BrandContextService, BrandProfile
from .content_strategy import ContentStrategyService, ContentStrategy
from .platform_adaptation import PlatformAdaptationService
from .component_generators import HookGenerator, CaptionGenerator, CallToActionGenerator, HashtagGenerator
from .content_quality import ContentQualityEvaluator
from .content_variation import ContentVariationGenerator
from .ai_providers import AIProviderManager


class ContentGenerationService:
    def __init__(self, provider_manager: AIProviderManager | None = None) -> None:
        self.provider_manager = provider_manager or AIProviderManager()

    async def generate(
        self,
        brand_payload: dict[str, Any],
        objective: str | None = None,
        category: str | None = None,
        platform: str = "linkedin",
        content_format: str = "post",
        instructions: str | None = None,
        trend: dict[str, Any] | None = None,
        provider: str = "internal",
        model: str | None = None,
        include_variations: bool = False,
        variation_count: int = 3,
    ) -> dict[str, Any]:
        # 1. Brand Context
        brand: BrandProfile = BrandContextService.parse(brand_payload)

        # 2. Strategy Formulation
        strategy: ContentStrategy = ContentStrategyService.develop_strategy(
            brand=brand,
            objective=objective,
            category=category,
            platform=platform,
            trend=trend,
            instructions=instructions,
        )

        # 3. Provider Availability Check
        provider_norm = provider.lower().strip()
        provider_info = self.provider_manager.check_availability(provider_norm)
        if provider_norm != "internal" and not provider_info["available"]:
            raise RuntimeError(
                f"Selected AI provider '{provider}' is not available or configured on the server. "
                "Configure valid credentials before requesting external generation."
            )

        # 4. Generate Core Content Components
        topic = strategy.topic
        hook = HookGenerator.generate(topic, platform, brand, style="contrarian")
        cta = CallToActionGenerator.generate(strategy.objective, platform, brand)
        hashtags = HashtagGenerator.generate(brand, topic, platform, count=5)

        # Construct body grounded in brand archetypes and trend evidence if present
        if trend and trend.get("title"):
            trend_title = trend.get("title")
            trend_desc = trend.get("description") or trend.get("summary") or ""
            sources = trend.get("sources") or []
            source_attribution = f" (Referencing analysis from {sources[0].get('source_name')})" if sources else ""

            body = (
                f"A recent shift in our space has caught widespread attention: '{trend_title}'{source_attribution}.\n\n"
                f"For {brand.audience}, this isn't just another headline—it reflects an evolving priority in {brand.niche}.\n\n"
                f"Key Takeaways:\n"
                f"• Understanding the context: {trend_desc[:120] if trend_desc else 'Timely shifts require deliberate adjustments.'}\n"
                f"• Practical application: Rather than reacting with hype, anchor your strategy in verified standards.\n"
                f"• The {brand.name} standard: Consistency in execution creates long-term resilience."
            )
        else:
            directives = BrandContextService.get_style_directives(brand)
            body = (
                f"{strategy.main_message}\n\n"
                f"In {brand.niche}, progress rarely happens through sudden breakthroughs. It happens through daily commitments.\n\n"
                f"Here are 3 core rules {brand.name} emphasizes:\n"
                f"1. Protect the fundamentals every single day.\n"
                f"2. Remove friction before attempting to add complexity.\n"
                f"3. Measure outcomes objectively, not based on transient motivation."
            )

        # If live AI provider requested and available, use real LLM generation
        if provider_norm != "internal":
            trend_info = f"Trend context: '{trend.get('title')}' - {trend.get('description', '')}" if trend and trend.get("title") else ""
            system_prompt = (
                f"You are the senior content strategist and writer for {brand.name}. "
                f"Industry: {brand.industry} ({brand.niche}). Target audience: {brand.audience}. "
                f"Tone: {brand.tone}. Archetype: {brand.archetype}. "
                "Write original, compelling social media copy. Respond strictly in JSON format with keys 'hook', 'body', 'call_to_action'."
            )
            user_prompt = (
                f"Platform: {platform}. Format: {content_format}. Objective: {strategy.objective}. "
                f"Topic: {topic}. {trend_info} Instructions: {instructions or 'Deliver high-value actionable insight.'}"
            )
            ai_data = await self.provider_manager.generate_with_external(
                provider=provider_norm,
                prompt=user_prompt,
                system_prompt=system_prompt,
                brand=brand,
            )
            if isinstance(ai_data, dict):
                if ai_data.get("hook"):
                    hook = str(ai_data["hook"])
                if ai_data.get("body"):
                    body = str(ai_data["body"])
                if ai_data.get("call_to_action"):
                    cta = str(ai_data["call_to_action"])

        title = f"{brand.name}: {strategy.topic[:50]}"
        caption = CaptionGenerator.generate(title, hook, platform, brand)

        # 5. Platform-specific adaptation
        adapted = PlatformAdaptationService.format_content_for_platform(
            platform=platform,
            title=title,
            hook=hook,
            body=body,
            caption=caption,
            call_to_action=cta,
            hashtags=hashtags,
            content_format=content_format,
        )

        # 6. Quality Evaluation
        quality_eval = ContentQualityEvaluator.evaluate(
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

        # 7. Optional Variations
        variations: list[dict[str, Any]] = []
        if include_variations:
            variations = ContentVariationGenerator.generate_variations(
                base_title=adapted["title"],
                base_body=adapted["body"],
                topic=topic,
                platform=platform,
                brand=brand,
                count=variation_count,
                trend=trend,
            )

        return {
            "title": adapted["title"],
            "hook": adapted["hook"],
            "body": adapted["body"],
            "caption": adapted["caption"],
            "call_to_action": adapted["call_to_action"],
            "hashtags": adapted["hashtags"],
            "platform": platform,
            "content_format": content_format,
            "category": strategy.category,
            "objective": strategy.objective,
            "topic": strategy.topic,
            "strategy": strategy.to_dict(),
            "quality_score": quality_eval["quality_score"],
            "quality_evaluation": quality_eval,
            "variations": variations,
            "ai_provider": provider_norm,
            "ai_model": model or provider_info.get("model", "socialpilot-deterministic-engine"),
            "is_ai_generated": provider_norm != "internal",
            "format_notes": adapted.get("format_notes", ""),
        }
