from __future__ import annotations

import re
from datetime import datetime, timezone
from typing import Any

from .context import BrandContext

_WORD = re.compile(r"[^\W_]+", re.UNICODE)


def tokens(value: str) -> set[str]:
    return {word.casefold() for word in _WORD.findall(value) if len(word) > 2}


def analyze_relevance(
    context: BrandContext,
    title: str,
    description: str,
    published_at: datetime | None,
    geographic_market: str | None,
) -> tuple[int, str]:
    corpus = f"{title} {description}".casefold()
    matched_keywords = [term for term in context.keywords if term.casefold() in corpus]
    matched_interests = [term for term in context.audience_interests if term.casefold() in corpus]
    domain_terms = [term for term in (context.industry, context.niche) if term]
    matched_domain = [term for term in domain_terms if term.casefold() in corpus]

    score = 0
    reasons = []
    if context.keywords:
        contribution = min(50, round(50 * len(matched_keywords) / len(context.keywords)))
        score += contribution
        if matched_keywords:
            reasons.append(f"matches brand keywords: {', '.join(matched_keywords)}")
    if domain_terms:
        contribution = round(20 * len(matched_domain) / len(domain_terms))
        score += contribution
        if matched_domain:
            reasons.append(f"matches brand industry or niche: {', '.join(matched_domain)}")
    if context.audience_interests:
        contribution = min(15, round(15 * len(matched_interests) / len(context.audience_interests)))
        score += contribution
        if matched_interests:
            reasons.append(f"overlaps audience interests: {', '.join(matched_interests)}")
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

    if published_at:
        age_days = max(0, (datetime.now(timezone.utc) - _utc(published_at)).total_seconds() / 86400)
        if age_days <= 7:
            score += 5
        elif age_days <= 30:
            score += 3
    else:
        reasons.append("publication date is unavailable")

    candidate_tokens = tokens(f"{title} {description}")
    positive_match = _feedback_similarity(candidate_tokens, context.positive_feedback)
    negative_match = _feedback_similarity(candidate_tokens, context.negative_feedback)
    feedback_adjustment = min(10, round(10 * positive_match)) - min(
        15, round(15 * negative_match)
    )
    score += feedback_adjustment
    if positive_match:
        reasons.append("similar to topics previously marked relevant or saved")
    if negative_match:
        reasons.append("similar to topics previously marked irrelevant or dismissed")

    if not matched_keywords and not matched_domain and not matched_interests:
        reasons.insert(0, "no direct brand keyword, niche, industry, or audience-interest match")
    return max(0, min(100, score)), "; ".join(reasons)


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
