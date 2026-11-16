from __future__ import annotations

import base64
import os
from typing import Any

import httpx

from .brand_visual_intelligence import BrandVisualIntelligenceService
from .media_provider_manager import MediaProviderManager
from .media_processing import MediaProcessingService


class ImageGenerationService:
    """Handles image generation across multiple AI providers."""

    _visual_intel = BrandVisualIntelligenceService()
    _provider_manager = MediaProviderManager()
    _media_processing = MediaProcessingService()

    def __init__(self) -> None:
        pass

    # ------------------------------------------------------------------
    # Provider-specific image generation
    # ------------------------------------------------------------------

    @classmethod
    async def _generate_openai(
        cls,
        prompt: str,
        negative_prompt: str,
        model: str,
        aspect_ratio: str,
        openai_key: str,
    ) -> dict[str, Any]:
        """Generate image via OpenAI Images API."""
        size = cls._media_processing.get_size_for_provider(aspect_ratio, "openai")
        width, height = cls._media_processing.aspect_ratio_to_dimensions(aspect_ratio)

        async with httpx.AsyncClient(timeout=httpx.Timeout(60.0)) as client:
            resp = await client.post(
                "https://api.openai.com/v1/images/generations",
                headers={
                    "Authorization": f"Bearer {openai_key}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": model,
                    "prompt": prompt,
                    "n": 1,
                    "size": size,
                    "response_format": "url",
                },
            )
            resp.raise_for_status()
            data = resp.json()
            image_url: str = data["data"][0]["url"]

        return {
            "success": True,
            "provider": "openai",
            "model": model,
            "image_url": image_url,
            "image_base64": None,
            "image_format": "png",
            "prompt_used": prompt,
            "width": width,
            "height": height,
            "aspect_ratio": aspect_ratio,
        }

    @classmethod
    async def _generate_stability(
        cls,
        prompt: str,
        negative_prompt: str,
        model: str,
        aspect_ratio: str,
        stability_key: str,
    ) -> dict[str, Any]:
        """Generate image via Stability AI API."""
        width, height = cls._media_processing.aspect_ratio_to_dimensions(aspect_ratio, base_size=1024)
        engine_id = model

        async with httpx.AsyncClient(timeout=httpx.Timeout(60.0)) as client:
            resp = await client.post(
                f"https://api.stability.ai/v1/generation/{engine_id}/text-to-image",
                headers={
                    "Authorization": f"Bearer {stability_key}",
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                },
                json={
                    "text_prompts": [
                        {"text": prompt, "weight": 1.0},
                        {"text": negative_prompt, "weight": -1.0},
                    ],
                    "cfg_scale": 7,
                    "height": height,
                    "width": width,
                    "samples": 1,
                    "steps": 30,
                },
            )
            resp.raise_for_status()
            data = resp.json()
            artifact = data["artifacts"][0]
            image_base64: str = artifact["base64"]

        return {
            "success": True,
            "provider": "stability",
            "model": model,
            "image_url": None,
            "image_base64": image_base64,
            "image_format": "png",
            "prompt_used": prompt,
            "width": width,
            "height": height,
            "aspect_ratio": aspect_ratio,
        }

    @classmethod
    async def _generate_gemini(
        cls,
        prompt: str,
        model: str,
        aspect_ratio: str,
        gemini_key: str,
    ) -> dict[str, Any]:
        """Generate image via Gemini Imagen API."""
        width, height = cls._media_processing.aspect_ratio_to_dimensions(aspect_ratio)

        async with httpx.AsyncClient(timeout=httpx.Timeout(60.0)) as client:
            resp = await client.post(
                f"https://generativelanguage.googleapis.com/v1beta/models/{model}:predict",
                params={"key": gemini_key},
                headers={"Content-Type": "application/json"},
                json={
                    "instances": [{"prompt": prompt}],
                    "parameters": {"sampleCount": 1},
                },
            )
            resp.raise_for_status()
            data = resp.json()
            prediction = data["predictions"][0]
            # Gemini returns bytesBase64Encoded in the prediction
            image_base64: str = prediction.get("bytesBase64Encoded") or prediction.get("image", {}).get("imageBytes", "")

        return {
            "success": True,
            "provider": "gemini",
            "model": model,
            "image_url": None,
            "image_base64": image_base64,
            "image_format": "png",
            "prompt_used": prompt,
            "width": width,
            "height": height,
            "aspect_ratio": aspect_ratio,
        }

    # ------------------------------------------------------------------
    # Main generation entrypoint
    # ------------------------------------------------------------------

    @classmethod
    async def generate_image(
        cls,
        brand_payload: dict[str, Any],
        visual_settings: dict[str, Any] | None = None,
        objective: str = "social_post",
        visual_style: str | None = None,
        platform: str | None = None,
        aspect_ratio: str | None = None,
        instructions: str | None = None,
        provider: str | None = None,
        draft: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        """Generate an image for the brand using the best available provider."""

        # Merge visual_settings into brand_payload for profile building
        merged_payload = dict(brand_payload)
        if visual_settings:
            merged_payload["visual_settings"] = visual_settings

        # Build visual profile
        visual_profile = cls._visual_intel.build_visual_profile(merged_payload, visual_settings)

        # Override visual_identity from visual_style arg if provided
        if visual_style:
            visual_profile = dict(visual_profile)
            visual_profile["visual_identity"] = visual_style

        # Determine aspect ratio
        effective_aspect_ratio = aspect_ratio or "1:1"

        # Build prompts
        # Enrich objective with draft context if available
        effective_objective = objective
        if draft:
            title = draft.get("title") or draft.get("hook") or ""
            if title:
                effective_objective = f"{objective}: {title}"

        prompt = cls._visual_intel.build_image_prompt(
            visual_profile=visual_profile,
            objective=effective_objective,
            instructions=instructions,
            platform=platform,
        )
        negative_prompt = cls._visual_intel.build_negative_prompt(visual_profile)

        # Determine provider
        if provider and provider != "auto":
            provider_info = cls._provider_manager.check_image_provider(provider)
        else:
            provider_info = cls._provider_manager.get_available_image_provider()

        if not provider_info or not provider_info.get("available"):
            return {
                "success": False,
                "status": "configuration_required",
                "message": (
                    "No image generation provider configured. "
                    "Set OPENAI_API_KEY, STABILITY_API_KEY, or GEMINI_API_KEY."
                ),
                "visual_profile": visual_profile,
                "prompt_used": prompt,
            }

        selected_provider = provider_info["provider"]
        model = provider_info["model"]

        try:
            if selected_provider == "openai":
                result = await cls._generate_openai(
                    prompt=prompt,
                    negative_prompt=negative_prompt,
                    model=model,
                    aspect_ratio=effective_aspect_ratio,
                    openai_key=os.getenv("OPENAI_API_KEY", ""),
                )
            elif selected_provider == "stability":
                result = await cls._generate_stability(
                    prompt=prompt,
                    negative_prompt=negative_prompt,
                    model=model,
                    aspect_ratio=effective_aspect_ratio,
                    stability_key=os.getenv("STABILITY_API_KEY", ""),
                )
            elif selected_provider == "gemini":
                result = await cls._generate_gemini(
                    prompt=prompt,
                    model=model,
                    aspect_ratio=effective_aspect_ratio,
                    gemini_key=os.getenv("GEMINI_API_KEY", ""),
                )
            else:
                return {
                    "success": False,
                    "status": "unsupported_provider",
                    "error": f"Provider '{selected_provider}' is not supported for image generation.",
                    "provider": selected_provider,
                }

            result["visual_profile"] = visual_profile
            return result

        except httpx.HTTPStatusError as exc:
            return {
                "success": False,
                "error": f"HTTP {exc.response.status_code}: {exc.response.text[:500]}",
                "provider": selected_provider,
                "model": model,
                "visual_profile": visual_profile,
            }
        except httpx.RequestError as exc:
            return {
                "success": False,
                "error": f"Request error: {str(exc)}",
                "provider": selected_provider,
                "model": model,
                "visual_profile": visual_profile,
            }
        except Exception as exc:
            return {
                "success": False,
                "error": str(exc),
                "provider": selected_provider,
                "model": model,
                "visual_profile": visual_profile,
            }
