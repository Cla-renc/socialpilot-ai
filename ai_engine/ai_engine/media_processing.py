from __future__ import annotations

from typing import Any


class MediaProcessingService:
    """Service providing platform formats, aspect ratio calculations, and provider size mappings."""

    PLATFORM_FORMATS: dict[str, dict[str, Any]] = {
        "instagram_feed": {"aspect_ratio": "1:1", "width": 1080, "height": 1080, "format": "jpg"},
        "instagram_story": {"aspect_ratio": "9:16", "width": 1080, "height": 1920, "format": "jpg"},
        "instagram_reel": {"aspect_ratio": "9:16", "width": 1080, "height": 1920, "format": "mp4"},
        "facebook_feed": {"aspect_ratio": "1.91:1", "width": 1200, "height": 628, "format": "jpg"},
        "facebook_story": {"aspect_ratio": "9:16", "width": 1080, "height": 1920, "format": "jpg"},
        "tiktok_video": {"aspect_ratio": "9:16", "width": 1080, "height": 1920, "format": "mp4"},
        "youtube_thumbnail": {"aspect_ratio": "16:9", "width": 1280, "height": 720, "format": "jpg"},
        "youtube_banner": {"aspect_ratio": "16:9", "width": 2560, "height": 1440, "format": "jpg"},
        "linkedin_post": {"aspect_ratio": "1.91:1", "width": 1200, "height": 627, "format": "jpg"},
        "linkedin_story": {"aspect_ratio": "9:16", "width": 1080, "height": 1920, "format": "jpg"},
    }

    @classmethod
    def get_format(cls, platform_use_case: str) -> dict[str, Any] | None:
        """Get format specifications for a specific platform use case."""
        return cls.PLATFORM_FORMATS.get(platform_use_case.lower().strip())

    @classmethod
    def get_recommended_formats(cls, platform: str) -> list[dict[str, Any]]:
        """Get all format recommendations for a given platform (e.g. 'instagram', 'facebook')."""
        p = platform.lower().strip()
        results: list[dict[str, Any]] = []
        for key, config in cls.PLATFORM_FORMATS.items():
            if key.startswith(p):
                item = dict(config)
                item["use_case"] = key
                results.append(item)
        return results

    @staticmethod
    def aspect_ratio_to_dimensions(aspect_ratio: str, base_size: int = 1080) -> tuple[int, int]:
        """Convert an aspect ratio string like '1:1', '16:9', '9:16', '4:5', '1.91:1' into (width, height)."""
        ar = aspect_ratio.strip().lower()

        if ar == "1:1":
            return (base_size, base_size)
        if ar == "16:9":
            # standard 16:9
            return (int(base_size * 16 / 9), base_size) if base_size < 1080 else (1920, 1080)
        if ar == "9:16":
            # standard 9:16
            return (base_size, int(base_size * 16 / 9)) if base_size <= 1080 else (1080, 1920)
        if ar == "4:5":
            return (1080, 1350)
        if ar in ("1.91:1", "1.9:1"):
            return (1200, 628)

        # Generic ratio parsing if possible "W:H"
        if ":" in ar:
            try:
                w_str, h_str = ar.split(":", 1)
                w_ratio = float(w_str)
                h_ratio = float(h_str)
                if h_ratio > 0:
                    calculated_w = int(base_size * (w_ratio / h_ratio))
                    return (calculated_w, base_size)
            except (ValueError, ZeroDivisionError):
                pass

        return (base_size, base_size)

    @staticmethod
    def get_size_for_provider(aspect_ratio: str, provider: str) -> str:
        """Return provider-specific size string (e.g. for OpenAI DALL-E 3: '1024x1024', '1792x1024', '1024x1792')."""
        p = provider.lower().strip()
        ar = aspect_ratio.strip().lower()

        if p == "openai":
            # OpenAI DALL-E 3 supports: 1024x1024, 1024x1792, 1792x1024
            if ar in ("16:9", "1.91:1", "1.9:1"):
                return "1792x1024"
            if ar in ("9:16", "4:5"):
                return "1024x1792"
            return "1024x1024"

        if p == "stability":
            # Stability SDXL supports resolutions close to 1024x1024
            if ar == "16:9":
                return "1344x768"
            if ar == "9:16":
                return "768x1344"
            if ar == "4:5":
                return "896x1152"
            if ar in ("1.91:1", "1.9:1"):
                return "1536x640"
            return "1024x1024"

        # Fallback default
        return "1024x1024"
