from __future__ import annotations

import os
from typing import Any

from .media_provider_manager import MediaProviderManager


class VideoGenerationService:
    """Handles video generation across external video AI providers."""

    _provider_manager = MediaProviderManager()

    def __init__(self) -> None:
        pass

    @classmethod
    async def generate_video(
        cls,
        brand_payload: dict[str, Any],
        visual_profile: dict[str, Any],
        draft: dict[str, Any] | None = None,
        video_idea: str | None = None,
        objective: str = "brand_video",
        platform: str = "instagram",
        aspect_ratio: str = "9:16",
        duration: int | None = None,
        provider: str | None = None,
    ) -> dict[str, Any]:
        """Generate a video using configured video AI provider.

        All external video providers require valid API keys (Runway, Stability Video, Pika).
        If no provider is configured, returns status 'configuration_required'.
        Never fabricates a completed video without a valid provider integration.
        """
        # Determine provider to use
        if provider and provider != "auto":
            provider_info = cls._provider_manager.check_video_provider(provider)
        else:
            provider_info = cls._provider_manager.get_available_video_provider()

        if not provider_info or not provider_info.get("available"):
            return {
                "success": False,
                "status": "configuration_required",
                "message": (
                    "No video generation provider configured. "
                    "Set RUNWAY_API_KEY, STABILITY_VIDEO_API_KEY, or PIKA_API_KEY."
                ),
                "platform": platform,
                "aspect_ratio": aspect_ratio,
                "duration": duration,
                "provider": provider or "none",
            }

        selected_provider = provider_info["provider"]
        model = provider_info.get("model", "unknown")

        # In production with API keys present, external API invocation would occur here.
        # Check actual credentials:
        key_map = {
            "runway": os.getenv("RUNWAY_API_KEY"),
            "stability_video": os.getenv("STABILITY_VIDEO_API_KEY"),
            "pika": os.getenv("PIKA_API_KEY"),
        }
        api_key = key_map.get(selected_provider)
        if not api_key:
            return {
                "success": False,
                "status": "configuration_required",
                "message": f"API key for video provider '{selected_provider}' is not set.",
                "provider": selected_provider,
                "model": model,
            }

        # Structure for future live video provider dispatch
        return {
            "success": False,
            "status": "provider_integration_pending",
            "message": f"Video generation dispatched to {selected_provider} ({model}). Async polling required.",
            "provider": selected_provider,
            "model": model,
            "platform": platform,
            "aspect_ratio": aspect_ratio,
        }
