from __future__ import annotations

import re
from datetime import datetime, timezone
from typing import Any

from .context import BrandContext

_WORD = re.compile(r"[^\W_]+", re.UNICODE)

# ---------------------------------------------------------------------------
# Domain/category semantic expansion map.
# Maps an industry or category term → frozenset of associated domain tokens.
# Used to give contextual relevance to topics that do not contain the literal
# industry word but clearly fall within that industry's discourse.
# Zero-cost: purely deterministic, no API calls.
# ---------------------------------------------------------------------------
_DOMAIN_EXPANSION: dict[str, frozenset[str]] = {
    "technology": frozenset({
        "ai", "ml", "software", "tech", "app", "cloud", "cyber", "cybersecurity",
        "startup", "data", "digital", "apple", "google", "microsoft", "meta",
        "openai", "nvidia", "chip", "semiconductor", "blockchain", "crypto",
        "robot", "automation", "saas", "api", "mobile", "device", "internet",
        "broadband", "5g", "quantum", "privacy", "hack", "breach", "algorithm",
        "neural", "llm", "chatgpt", "generative",
    }),
    "marketing": frozenset({
        "brand", "advertising", "campaign", "social", "content", "seo",
        "influencer", "engagement", "viral", "conversion", "roi", "funnel",
        "email", "b2b", "b2c", "cpm", "cpc", "analytics", "growth", "retention",
    }),
    "finance": frozenset({
        "bank", "stock", "invest", "market", "fund", "equity", "bond", "rate",
        "inflation", "recession", "crypto", "bitcoin", "fintech", "mortgage",
        "loan", "tax", "gdp", "federal", "interest", "portfolio",
    }),
    "health": frozenset({
        "medical", "hospital", "drug", "vaccine", "health", "wellness", "diet",
        "fitness", "mental", "therapy", "clinical", "pharma", "nutrition",
        "weight", "exercise", "sleep", "disease", "cancer", "diabetes",
    }),
    "fitness": frozenset({
        "workout", "gym", "exercise", "training", "muscle", "cardio", "yoga",
        "run", "strength", "nutrition", "diet", "athlete", "sport", "weight",
        "physique", "coaching", "wellness",
    }),
    "ecommerce": frozenset({
        "shop", "store", "product", "sale", "discount", "shipping", "amazon",
        "retail", "consumer", "brand", "review", "cart", "checkout", "online",
    }),
    "education": frozenset({
        "learn", "school", "university", "course", "study", "student", "degree",
        "teach", "online", "edtech", "skill", "training", "academic", "research",
        "scholarship", "curriculum",
    }),
    "food": frozenset({
        "restaurant", "recipe", "food", "meal", "diet", "cuisine", "chef",
        "ingredient", "nutrition", "beverage", "drink", "coffee", "vegan",
        "organic", "grocery", "takeout",
    }),
    "travel": frozenset({
        "trip", "flight", "hotel", "vacation", "tour", "destination", "airline",
        "booking", "passport", "visa", "cruise", "resort", "adventure", "tourism",
    }),
    "real estate": frozenset({
        "property", "house", "mortgage", "rent", "lease", "home", "apartment",
        "invest", "market", "agent", "listing", "buyer", "seller", "construction",
    }),
    "outdoor equipment": frozenset({
        "hiking", "trail", "camping", "gear", "outdoor", "backpack", "tent",
        "mountain", "climb", "adventure", "nature", "park", "wilderness",
    }),
}


def _expand_domain(term: str) -> frozenset[str]:
    """Return domain-specific associated tokens for a given industry/category term."""
    normalized = term.strip().casefold()
    for key, expansion in _DOMAIN_EXPANSION.items():
        if key in normalized or normalized in key:
            return expansion
    return frozenset()


def tokens(value: str) -> set[str]:
    return {word.casefold() for word in _WORD.findall(value) if len(word) >= 2}


# ---------------------------------------------------------------------------
# Relevance tier constants
# ---------------------------------------------------------------------------
TIER_DIRECT = "direct"            # score 75-100
TIER_CONTEXTUAL = "contextual"    # score 50-74
TIER_ADJACENT = "adjacent"        # score 25-49
TIER_IRRELEVANT = "irrelevant"    # score  0-24


def relevance_tier(score: int) -> str:
    if score >= 75:
        return TIER_DIRECT
    if score >= 50:
        return TIER_CONTEXTUAL
    if score >= 25:
        return TIER_ADJACENT
    return TIER_IRRELEVANT


def analyze_relevance(
    context: BrandContext,
    title: str,
    description: str,
    published_at: datetime | None,
    geographic_market: str | None,
) -> tuple[int, str]:
    corpus = f"{title} {description}".casefold()
    corpus_tokens = tokens(f"{title} {description}")

    reasons: list[str] = []
    score = 0

    # 1. Explicit brand keywords (up to 50 pts) — highest precision
    if context.keywords:
        matched_keywords = [kw for kw in context.keywords if kw.casefold() in corpus]
        contribution = min(50, round(50 * len(matched_keywords) / len(context.keywords)))
        score += contribution
        if matched_keywords:
            reasons.append(f"matches brand keywords: {', '.join(matched_keywords)}")

    # 2. Industry / niche exact match (up to 20 pts)
    domain_terms = [t for t in (context.industry, context.niche) if t]
    matched_domain: list[str] = []
    if domain_terms:
        matched_domain = [t for t in domain_terms if t.casefold() in corpus]
        if matched_domain:
            contribution = 20 if len(matched_domain) == len(domain_terms) else 15
            score += contribution
            reasons.append(f"matches brand industry or niche: {', '.join(matched_domain)}")

    # 3. Domain semantic expansion (up to 35 pts) — contextual breadth
    #    When literal industry/niche word is absent, checks whether corpus tokens overlap with associated terms.
    if not matched_domain:
        domain_expansion_tokens: frozenset[str] = frozenset()
        for term in domain_terms:
            domain_expansion_tokens |= _expand_domain(term)
        if context.category:
            domain_expansion_tokens |= _expand_domain(context.category)
        if domain_expansion_tokens:
            overlap = corpus_tokens & domain_expansion_tokens
            if overlap:
                contribution = min(35, 20 + (len(overlap) - 1) * 5)
                score += contribution
                # Only name top-3 matches to keep reason concise
                top = sorted(overlap)[:3]
                reasons.append(f"contextual domain match via {context.industry or context.category} terms: {', '.join(top)}")

    # 4. Brand description / category signal (up to 10 pts)
    desc_tokens = tokens(f"{context.description or ''} {context.category or ''}")
    if desc_tokens:
        overlap = corpus_tokens & desc_tokens
        if overlap:
            contribution = min(10, len(overlap) * 2)
            score += contribution
            top = sorted(overlap)[:3]
            reasons.append(f"overlaps brand description or category: {', '.join(top)}")

    # 5. Audience interests (up to 15 pts)
    if context.audience_interests:
        matched_interests = [ai for ai in context.audience_interests if ai.casefold() in corpus]
        contribution = min(15, round(15 * len(matched_interests) / len(context.audience_interests)))
        score += contribution
        if matched_interests:
            reasons.append(f"overlaps audience interests: {', '.join(matched_interests)}")

    # 6. Geographic market (up to 10 pts)
    if geographic_market:
        if geographic_market.casefold() in corpus:
            score += 10
            reasons.append(f"explicitly mentions {geographic_market}")
    elif context.region:
        score += 5
        reasons.append("geographic fit is unknown; no regional match was assumed")
    else:
        score += 5
        reasons.append("geographic evidence is unavailable")

    # 7. Freshness (up to 5 pts)
    if published_at:
        age_days = max(0, (datetime.now(timezone.utc) - _utc(published_at)).total_seconds() / 86400)
        if age_days <= 7:
            score += 5
        elif age_days <= 30:
            score += 3
    else:
        reasons.append("publication date is unavailable")

    # 8. User feedback signals (±15 pts)
    candidate_tokens = corpus_tokens
    positive_match = _feedback_similarity(candidate_tokens, context.positive_feedback)
    negative_match = _feedback_similarity(candidate_tokens, context.negative_feedback)
    feedback_adjustment = min(10, round(10 * positive_match)) - min(15, round(15 * negative_match))
    score += feedback_adjustment
    if positive_match:
        reasons.append("similar to topics previously marked relevant or saved")
    if negative_match:
        reasons.append("similar to topics previously marked irrelevant or dismissed")

    if not reasons:
        reasons.insert(0, "no brand keyword, niche, industry, category, description, or audience-interest match")

    final = max(0, min(100, score))
    return final, "; ".join(reasons)


def _feedback_similarity(candidate: set[str], examples: tuple[str, ...]) -> float:
    similarities = []
    for example in examples:
        example_tokens = tokens(example)
        if example_tokens:
            similarities.append(len(candidate & example_tokens) / len(example_tokens))
    return max(similarities, default=0.0)


def rank_opportunity(
    relevance_score: int,
    published_at: datetime | None,
    geographic_market: str | None,
    observed_market: str | None,
    source_reliability: int = 65,
) -> int:
    if published_at is None:
        timeliness = 40
    else:
        age_days = max(0, (datetime.now(timezone.utc) - _utc(published_at)).total_seconds() / 86400)
        timeliness = 100 if age_days <= 1 else 85 if age_days <= 7 else 60 if age_days <= 30 else 30
    if not geographic_market:
        geographic = 50
    elif observed_market and geographic_market.casefold() in observed_market.casefold():
        geographic = 100
    else:
        geographic = 0
    # Relevance 45%, timeliness 25%, source reliability 20%, geography 10%.
    return round(
        relevance_score * 0.45
        + timeliness * 0.25
        + source_reliability * 0.20
        + geographic * 0.10
    )


def _utc(value: datetime) -> datetime:
    return value.replace(tzinfo=timezone.utc) if value.tzinfo is None else value.astimezone(timezone.utc)
