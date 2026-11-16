from __future__ import annotations

from dataclasses import dataclass
from typing import Any


def _strings(value: Any) -> list[str]:
    if isinstance(value, str):
        return [part.strip() for part in value.replace("\n", ",").split(",") if part.strip()]
    if isinstance(value, list):
        return [str(part).strip() for part in value if str(part).strip()]
    return []


@dataclass(frozen=True)
class BrandContext:
    industry: str | None
    niche: str | None
    audience: str | None
    audience_interests: tuple[str, ...]
    region: str | None
    language: str | None
    keywords: tuple[str, ...]
    objectives: tuple[str, ...]
    content_categories: tuple[str, ...]
    positive_feedback: tuple[str, ...]
    negative_feedback: tuple[str, ...]

    @classmethod
    def from_payload(cls, brand: dict[str, Any]) -> BrandContext:
        settings = brand.get("settings") or {}
        feedback = brand.get("trend_feedback") or []
        return cls(
            industry=brand.get("industry"),
            niche=brand.get("niche"),
            audience=brand.get("audience"),
            audience_interests=tuple(_strings(brand.get("audience_interests"))),
            region=brand.get("region"),
            language=brand.get("language"),
            keywords=tuple(_strings(settings.get("brand_keywords"))),
            objectives=tuple(_strings(settings.get("marketing_objectives"))),
            content_categories=tuple(_strings(settings.get("content_formats"))),
            positive_feedback=tuple(
                item["title"]
                for item in feedback
                if item.get("feedback") in {"relevant", "saved"} and item.get("title")
            ),
            negative_feedback=tuple(
                item["title"]
                for item in feedback
                if item.get("feedback") in {"irrelevant", "dismissed"} and item.get("title")
            ),
        )

    def research_terms(self) -> tuple[str, ...]:
        terms = [
            *self.keywords,
            *self.audience_interests,
            self.niche or "",
            self.industry or "",
        ]
        return tuple(dict.fromkeys(term for term in (item.strip() for item in terms) if term))


def generate_queries(context: BrandContext) -> list[str]:
    queries = []
    for term in context.research_terms():
        queries.append(term)
        if context.region:
            queries.append(f"{term} {context.region}")
    return list(dict.fromkeys(queries))[:30]
