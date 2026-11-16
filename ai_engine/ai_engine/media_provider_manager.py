from __future__ import annotations

import os
from typing import Any


class MediaProviderManager:
    """Manages image and video generation provider availability."""

    IMAGE_PROVIDERS = ["openai", "stability", "gemini", "internal"]
    VIDEO_PROVIDERS = ["runway", "stability_video", "pika"]

    def check_image_provider(self, provider: str) -> dict[str, Any]:
        """Return availability info for an image provider."""
        p = provider.lower().strip()

        if p == "openai":
            key = os.getenv("OPENAI_API_KEY", "")
            available = bool(key)
            return {
                "provider": "openai",
                "available": available,
                "status": "ready" if available else "configuration_required",
                "model": os.getenv("OPENAI_IMAGE_MODEL", "dall-e-3"),
                "capabilities": ["generate", "edit", "variations"],
            }

        if p == "stability":
            key = os.getenv("STABILITY_API_KEY", "")
            available = bool(key)
            return {
                "provider": "stability",
                "available": available,
                "status": "ready" if available else "configuration_required",
                "model": os.getenv("STABILITY_MODEL", "stable-diffusion-xl-1024-v1-0"),
                "capabilities": ["generate"],
            }

        if p == "gemini":
            key = os.getenv("GEMINI_API_KEY", "")
            available = bool(key)
            return {
                "provider": "gemini",
                "available": available,
                "status": "ready" if available else "configuration_required",
                "model": os.getenv("GEMINI_IMAGE_MODEL", "imagen-3.0-generate-002"),
                "capabilities": ["generate"],
            }

        if p == "internal":
            return {
                "provider": "internal",
                "available": True,
                "status": "ready_no_generation",
                "model": "placeholder",
                "capabilities": ["placeholder_only"],
            }

        return {
            "provider": p,
            "available": False,
            "status": "unsupported_provider",
            "model": "unknown",
            "capabilities": [],
        }

    def check_video_provider(self, provider: str) -> dict[str, Any]:
        """Return availability info for a video provider."""
        p = provider.lower().strip()

        env_map = {
            "runway": ("RUNWAY_API_KEY", "runway-gen3-alpha"),
            "stability_video": ("STABILITY_VIDEO_API_KEY", "stable-video-diffusion-img2vid-xt"),
            "pika": ("PIKA_API_KEY", "pika-1.0"),
        }

        if p in env_map:
            env_var, default_model = env_map[p]
            key = os.getenv(env_var, "")
            available = bool(key)
            return {
                "provider": p,
                "available": available,
                "status": "ready" if available else "configuration_required",
                "model": os.getenv(f"{env_var.replace('API_KEY', 'MODEL')}", default_model),
                "capabilities": ["generate"],
            }

        return {
            "provider": p,
            "available": False,
            "status": "unsupported_provider",
            "model": "unknown",
            "capabilities": [],
        }

    def get_available_image_provider(self) -> dict[str, Any] | None:
        """Return first configured real image provider (openai, stability, gemini) or None."""
        for provider in ["openai", "stability", "gemini"]:
            info = self.check_image_provider(provider)
            if info["available"]:
                return info
        return None

    def get_available_video_provider(self) -> dict[str, Any] | None:
        """Return first configured video provider or None."""
        for provider in self.VIDEO_PROVIDERS:
            info = self.check_video_provider(provider)
            if info["available"]:
                return info
        return None

    def list_all_providers(self) -> dict[str, Any]:
        """Return all image and video providers with their status."""
        return {
            "image_providers": [self.check_image_provider(p) for p in self.IMAGE_PROVIDERS],
            "video_providers": [self.check_video_provider(p) for p in self.VIDEO_PROVIDERS],
        }
