from __future__ import annotations

import json
import os
from typing import Any
import httpx
from .brand_context import BrandProfile


class AIProviderManager:
    SUPPORTED_PROVIDERS = ["internal", "ollama", "openai", "gemini", "openrouter", "groq"]

    def __init__(self) -> None:
        self.ollama_base_url = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
        self.ollama_model = os.getenv("OLLAMA_MODEL", "llama3.2:3b")
        self.openai_key = os.getenv("OPENAI_API_KEY", "")
        self.gemini_key = os.getenv("GEMINI_API_KEY", "")
        self.openrouter_key = os.getenv("OPENROUTER_API_KEY", "")
        self.groq_key = os.getenv("GROQ_API_KEY", "")
        self.groq_base_url = os.getenv("GROQ_BASE_URL", "https://api.groq.com/openai/v1").rstrip("/")
        self.groq_model = os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile")

    def check_availability(self, provider: str) -> dict[str, Any]:
        p = provider.lower().strip()
        if p == "internal":
            return {"provider": "internal", "available": True, "model": "socialpilot-deterministic-engine", "status": "ready"}
        if p == "ollama":
            configured = bool(self.ollama_base_url and self.ollama_model)
            return {
                "provider": "ollama",
                "available": configured,
                "model": self.ollama_model,
                "status": "ready" if configured else "configuration_required",
            }
        if p == "openai":
            configured = bool(self.openai_key)
            return {
                "provider": "openai",
                "available": configured,
                "model": os.getenv("OPENAI_MODEL", "gpt-4o-mini"),
                "status": "ready" if configured else "configuration_required",
            }
        if p == "gemini":
            configured = bool(self.gemini_key)
            return {
                "provider": "gemini",
                "available": configured,
                "model": os.getenv("GEMINI_MODEL", "gemini-1.5-flash"),
                "status": "ready" if configured else "configuration_required",
            }
        if p == "openrouter":
            configured = bool(self.openrouter_key)
            return {
                "provider": "openrouter",
                "available": configured,
                "model": os.getenv("OPENROUTER_MODEL", "meta-llama/llama-3.1-8b-instruct:free"),
                "status": "ready" if configured else "configuration_required",
            }
        if p == "groq":
            configured = bool(self.groq_key)
            return {
                "provider": "groq",
                "available": configured,
                "model": self.groq_model,
                "status": "ready" if configured else "configuration_required",
            }
        return {"provider": p, "available": False, "model": "unknown", "status": "unsupported_provider"}

    async def generate_with_ollama(self, prompt: str, system_prompt: str) -> str:
        if not self.ollama_base_url:
            raise RuntimeError("Ollama base URL is not configured.")
        async with httpx.AsyncClient(timeout=30) as client:
            response = await client.post(
                f"{self.ollama_base_url}/api/generate",
                json={
                    "model": self.ollama_model,
                    "prompt": prompt,
                    "system": system_prompt,
                    "stream": False,
                    "format": "json",
                },
            )
            response.raise_for_status()
            data = response.json()
            return str(data.get("response", ""))

    async def generate_with_external(
        self,
        provider: str,
        prompt: str,
        system_prompt: str,
        brand: BrandProfile,
    ) -> dict[str, Any]:
        """Calls external AI provider if configured. Returns dict with generated text or raises exception."""
        p = provider.lower().strip()
        availability = self.check_availability(p)
        if not availability["available"]:
            raise RuntimeError(f"AI Provider '{p}' is not configured or unavailable on this server.")

        if p == "ollama":
            raw = await self.generate_with_ollama(prompt, system_prompt)
            try:
                parsed = json.loads(raw)
                if isinstance(parsed, dict) and isinstance(parsed.get("body"), list):
                    parsed["body"] = "\n".join(str(item) for item in parsed["body"])
                return parsed
            except Exception:
                return {"body": raw}

        # For external API providers when keys are set
        if p == "openai":
            async with httpx.AsyncClient(timeout=30) as client:
                resp = await client.post(
                    "https://api.openai.com/v1/chat/completions",
                    headers={"Authorization": f"Bearer {self.openai_key}"},
                    json={
                        "model": availability["model"],
                        "messages": [
                            {"role": "system", "content": system_prompt},
                            {"role": "user", "content": prompt},
                        ],
                        "response_format": {"type": "json_object"},
                    },
                )
                resp.raise_for_status()
                content = resp.json()["choices"][0]["message"]["content"]
                return json.loads(content)

        if p == "groq":
            async with httpx.AsyncClient(timeout=30) as client:
                resp = await client.post(
                    f"{self.groq_base_url}/chat/completions",
                    headers={"Authorization": f"Bearer {self.groq_key}"},
                    json={
                        "model": availability["model"],
                        "messages": [
                            {"role": "system", "content": system_prompt},
                            {"role": "user", "content": prompt},
                        ],
                        "response_format": {"type": "json_object"},
                    },
                )
                resp.raise_for_status()
                content = resp.json()["choices"][0]["message"]["content"]
                return json.loads(content)

        raise NotImplementedError(f"Direct integration for {p} requires live external network invocation.")
