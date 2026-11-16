"""Publishing Content Validator - Phase 9 SocialPilot AI"""
from ai_engine.platform_compatibility import PlatformCompatibility, PLATFORM_REQUIREMENTS


class PublishingContentValidator:
    def __init__(self):
        self.compatibility = PlatformCompatibility()

    def validate(self, platform: str, content: dict) -> dict:
        platform = platform.lower()
        reqs     = PLATFORM_REQUIREMENTS.get(platform, {})
        errors, warnings = [], []

        text     = content.get("text", "") or ""
        text_reqs = reqs.get("text", {})
        text_max  = text_reqs.get("max_chars", text_reqs.get("title_max", 5000))

        if platform == "youtube":
            title = content.get("title", "") or ""
            if not title:
                errors.append("YouTube posts require a video title")
            elif len(title) > 100:
                errors.append(f"YouTube title exceeds 100 character limit (current: {len(title)})")
        elif not text and not content.get("media_urls"):
            errors.append("Content must have text or media")

        if text and len(text) > text_max:
            errors.append(f"Text exceeds {platform} limit ({len(text)}/{text_max} characters)")

        media_urls   = content.get("media_urls", []) or []
        content_type = content.get("content_type", "text" if not media_urls else "image")

        if media_urls:
            compat = self.compatibility.check_compatibility(platform, content_type)
            if not compat["compatible"]:
                errors.extend(compat["errors"])
            warnings.extend(compat.get("warnings", []))

        if platform == "instagram" and not media_urls:
            errors.append("Instagram requires at least one image or video")
        if platform == "tiktok" and not media_urls:
            errors.append("TikTok requires a video")

        return {"valid": len(errors) == 0, "errors": errors, "warnings": warnings, "platform": platform}

    def validate_multi_platform(self, platforms: list, content: dict) -> dict:
        return {p: self.validate(p, content) for p in platforms}
