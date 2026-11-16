from __future__ import annotations

import json
import os

import httpx


class OllamaAngleGenerator:
    def __init__(
        self,
        base_url: str | None = None,
        model: str | None = None,
        enabled: bool | None = None,
    ) -> None:
        self.base_url = (base_url or os.getenv("OLLAMA_BASE_URL", "")).rstrip("/")
        self.model = model or os.getenv("OLLAMA_MODEL", "")
        self.enabled = (
            enabled if enabled is not None
            else os.getenv("TREND_ENABLE_OLLAMA_ANALYSIS", "false").casefold() == "true"
        )
        self.configured = bool(self.base_url and self.model)

    async def suggest(self, topic: str, evidence: list[str], niche: str | None) -> list[str]:
        if not self.enabled or not self.configured or not evidence:
            return []
        prompt = (
            "Suggest up to three short social content angles grounded only in the supplied "
            "source evidence. Do not add facts, statistics, dates, or claims. Return JSON "
            'as {"angles":["..."]}. These are ideas, not generated posts.\n'
            f"Brand niche: {niche or 'not supplied'}\n"
            f"Observed topic: {topic}\n"
            f"Source evidence: {json.dumps(evidence[:3], ensure_ascii=False)}"
        )
        async with httpx.AsyncClient(timeout=8) as client:
            response = await client.post(
                f"{self.base_url}/api/generate",
                json={
                    "model": self.model,
                    "prompt": prompt,
                    "stream": False,
                    "format": "json",
                },
            )
            response.raise_for_status()
        generated = json.loads(response.json().get("response", "{}"))
        angles = generated.get("angles", [])
        if not isinstance(angles, list):
            raise ValueError("Ollama response did not contain an angles list.")
        return [str(angle).strip()[:1000] for angle in angles[:3] if str(angle).strip()]
