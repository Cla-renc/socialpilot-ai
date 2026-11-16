from __future__ import annotations

from typing import Any


class BrandVisualIntelligenceService:
    """Builds visual brand profiles and image prompts from brand data."""

    # Visual identity heuristics keyword mapping
    _IDENTITY_RULES: list[tuple[str, list[str]]] = [
        ("dark_cinematic", ["fitness", "discipline", "martial arts", "mma", "boxing", "combat", "gym", "bodybuilding", "military", "stoic"]),
        ("product_ad", ["retail", "commerce", "shop", "store", "mart", "product", "apparel", "fashion", "ecommerce", "merchandise"]),
        ("commercial", ["commercial", "consumer", "fmcg", "cpg", "supermarket", "grocery", "food", "beverage"]),
        ("educational", ["education", "learning", "training", "school", "course", "tutoring", "university", "academy", "coaching", "certification"]),
        ("inspirational", ["wisdom", "philosophy", "lifestyle", "thought", "motivation", "mindset", "wellness", "spiritual", "meditation", "yoga"]),
        ("corporate", ["tech", "saas", "software", "corporate", "finance", "b2b", "fintech", "startup", "ai", "enterprise", "consulting", "legal"]),
    ]

    _LOGO_PLACEMENTS = {
        "dark_cinematic": "bottom_right",
        "commercial": "top_center",
        "product_ad": "top_right",
        "educational": "top_left",
        "inspirational": "bottom_center",
        "corporate": "top_left",
        "minimalist": "bottom_right",
        "editorial": "bottom_left",
    }

    _STYLE_PROMPTS: dict[str, str] = {
        "dark_cinematic": (
            "ultra-realistic cinematic photography, dramatic moody lighting, deep shadows, "
            "high-contrast, dark color palette with intense highlights, film grain texture, "
            "professional sports and fitness aesthetic, powerful composition"
        ),
        "commercial": (
            "bright, vibrant commercial photography, natural lighting, warm tones, "
            "lifestyle-driven product display, clean background, inviting and approachable, "
            "consumer-focused composition"
        ),
        "product_ad": (
            "professional product photography, clean isolated subject, studio lighting, "
            "sharp focus, premium product presentation, minimalist background, "
            "advertising-grade quality"
        ),
        "educational": (
            "clean infographic-style visual, bright pastel colors, clear typography emphasis, "
            "professional instructional aesthetic, organized layout, trust-inspiring design"
        ),
        "inspirational": (
            "warm golden-hour photography, soft bokeh, uplifting composition, "
            "peaceful and empowering mood, natural environments, authentic emotional storytelling"
        ),
        "corporate": (
            "professional corporate photography, clean modern aesthetic, neutral palette, "
            "sharp and authoritative composition, technology and business context, "
            "polished and trustworthy"
        ),
        "minimalist": (
            "minimalist design, abundant white space, clean lines, subtle color accents, "
            "elegant typography, modern and sophisticated aesthetic"
        ),
        "editorial": (
            "editorial-style photography, strong visual narrative, bold compositions, "
            "magazine-quality aesthetic, creative lighting, artistic direction"
        ),
    }

    _NEGATIVE_PROMPTS: dict[str, str] = {
        "dark_cinematic": "cartoon, anime, low quality, blurry, watermark, text overlay, bright pastel, childish, overexposed, flat lighting",
        "commercial": "dark, gloomy, low quality, blurry, watermark, overly complex, clinical, sterile",
        "product_ad": "cluttered background, poor lighting, blurry, watermark, text, low resolution, distorted product",
        "educational": "dark, cluttered, confusing, blurry, low quality, watermark, overly artistic",
        "inspirational": "dark, clinical, corporate, sterile, blurry, watermark, low quality, negative imagery",
        "corporate": "cartoon, casual, blurry, watermark, low quality, cluttered, unprofessional",
        "minimalist": "cluttered, busy, low quality, blurry, watermark, overly colorful, complex patterns",
        "editorial": "low quality, blurry, watermark, amateur, snapshot quality, overexposed",
    }

    @staticmethod
    def _determine_visual_identity(brand_payload: dict[str, Any]) -> str:
        """Determine visual identity from brand category/industry/niche heuristics."""
        searchable = " ".join([
            str(brand_payload.get("category") or ""),
            str(brand_payload.get("industry") or ""),
            str(brand_payload.get("niche") or ""),
            str(brand_payload.get("description") or ""),
        ]).lower()

        for identity, keywords in BrandVisualIntelligenceService._IDENTITY_RULES:
            if any(kw in searchable for kw in keywords):
                return identity

        return "minimalist"

    @classmethod
    def build_visual_profile(
        cls,
        brand_payload: dict[str, Any],
        visual_settings: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Build a structured visual profile from a brand payload dict."""
        # Allow direct visual_settings parameter or override from brand_payload
        settings_override: dict[str, Any] = dict(brand_payload.get("visual_settings") or {})
        if visual_settings:
            settings_override.update(visual_settings)

        brand_name = str(brand_payload.get("name") or "Brand").strip()

        # Determine visual identity — visual_settings can override
        visual_identity: str = (
            str(settings_override.get("visual_identity")).strip()
            if settings_override.get("visual_identity")
            else cls._determine_visual_identity(brand_payload)
        )

        # Colors — from visual_settings or defaults
        primary_color: str | None = settings_override.get("primary_color") or None
        secondary_color: str | None = settings_override.get("secondary_color") or None
        accent_color: str | None = settings_override.get("accent_color") or None

        # Fonts
        primary_font: str | None = settings_override.get("primary_font") or None
        secondary_font: str | None = settings_override.get("secondary_font") or None

        # Logo placement
        logo_placement: str = (
            settings_override.get("logo_placement")
            or cls._LOGO_PLACEMENTS.get(visual_identity, "bottom_right")
        )

        # Platforms — from brand or visual_settings
        preferred_platforms: list[str] = (
            settings_override.get("preferred_platforms")
            or brand_payload.get("connected_platforms")
            or ["instagram", "linkedin", "facebook"]
        )
        if isinstance(preferred_platforms, str):
            preferred_platforms = [p.strip() for p in preferred_platforms.split(",") if p.strip()]

        # Aspect ratios
        preferred_aspect_ratios: list[str] = (
            settings_override.get("preferred_aspect_ratios")
            or ["1:1", "9:16", "16:9"]
        )

        # Style directives (text paragraph)
        style_directives_base = cls._STYLE_PROMPTS.get(visual_identity, cls._STYLE_PROMPTS["minimalist"])
        tone = str(brand_payload.get("tone") or "professional").strip()
        audience = str(brand_payload.get("audience") or "general audience").strip()
        style_directives = (
            f"Brand: {brand_name}. Visual style: {visual_identity}. "
            f"Tone: {tone}. Target audience: {audience}. "
            f"Style: {style_directives_base}."
        )
        if settings_override.get("style_directives"):
            style_directives = str(settings_override["style_directives"])

        # Image generation prompts
        image_generation_style_prompt: str | None = (
            settings_override.get("image_generation_style_prompt")
            or style_directives_base
        )
        image_generation_negative_prompt: str | None = (
            settings_override.get("image_generation_negative_prompt")
            or cls._NEGATIVE_PROMPTS.get(visual_identity, "")
        )

        # Design preferences dict
        design_preferences: dict[str, Any] = {
            "visual_identity": visual_identity,
            "logo_placement": logo_placement,
            "text_overlay": settings_override.get("text_overlay", True),
            "border_style": settings_override.get("border_style", "none"),
            "background_style": settings_override.get("background_style", "contextual"),
            "image_quality": settings_override.get("image_quality", "high"),
        }
        if settings_override.get("design_preferences"):
            design_preferences.update(settings_override["design_preferences"])

        return {
            "brand_name": brand_name,
            "visual_identity": visual_identity,
            "primary_color": primary_color,
            "secondary_color": secondary_color,
            "accent_color": accent_color,
            "primary_font": primary_font,
            "secondary_font": secondary_font,
            "logo_placement": logo_placement,
            "preferred_platforms": preferred_platforms,
            "preferred_aspect_ratios": preferred_aspect_ratios,
            "style_directives": style_directives,
            "image_generation_style_prompt": image_generation_style_prompt,
            "image_generation_negative_prompt": image_generation_negative_prompt,
            "design_preferences": design_preferences,
        }

    @classmethod
    def build_image_prompt(
        cls,
        visual_profile: dict[str, Any],
        objective: str,
        instructions: str | None = None,
        platform: str | None = None,
    ) -> str:
        """Construct a detailed image generation prompt from the visual profile + objective."""
        brand_name = visual_profile.get("brand_name", "Brand")
        visual_identity = visual_profile.get("visual_identity", "minimalist")
        style_prompt = visual_profile.get("image_generation_style_prompt") or cls._STYLE_PROMPTS.get(visual_identity, "")
        style_directives = visual_profile.get("style_directives", "")

        parts: list[str] = []

        # Objective first
        parts.append(f"Create a professional image for {brand_name}.")
        parts.append(f"Objective: {objective}.")

        # Style
        if style_prompt:
            parts.append(style_prompt)

        # Platform-specific guidance
        if platform:
            platform_guidance = {
                "instagram": "optimized for Instagram feed, square or portrait format, visually striking",
                "instagram_story": "vertical 9:16 format, bold visuals, mobile-first design",
                "instagram_reel": "vertical 9:16 video thumbnail, dynamic and eye-catching",
                "facebook": "Facebook-optimized, engaging and shareable",
                "linkedin": "professional LinkedIn aesthetic, business-appropriate",
                "tiktok": "TikTok-style vertical format, trendy and energetic",
                "youtube": "YouTube thumbnail style, bold text-ready, high contrast",
                "twitter": "Twitter card format, clear and concise visual message",
            }
            guidance = platform_guidance.get(platform.lower(), f"optimized for {platform}")
            parts.append(guidance)

        # Color hints
        primary_color = visual_profile.get("primary_color")
        if primary_color:
            parts.append(f"Primary brand color: {primary_color}.")

        # Custom instructions override
        if instructions:
            parts.append(f"Additional requirements: {instructions}.")

        # Quality tags
        parts.append("4K resolution, photorealistic, high detail, professional quality.")

        return " ".join(parts)

    @classmethod
    def build_negative_prompt(cls, visual_profile: dict[str, Any]) -> str:
        """Return a negative prompt string for the visual profile."""
        visual_identity = visual_profile.get("visual_identity", "minimalist")
        base = cls._NEGATIVE_PROMPTS.get(visual_identity, "")
        universal = "nsfw, explicit content, violence, gore, disturbing imagery, copyrighted logos, text errors, deformed faces, extra limbs"
        if base:
            return f"{base}, {universal}"
        return universal
