from __future__ import annotations

import re
from typing import Any
from .brand_context import BrandProfile


class ContentQualityEvaluator:
    """
    Evaluates generated content using 9 documented criteria with transparent weighted scoring.
    NOTE: Quality evaluation measures structural, linguistic, and contextual adherence.
    It does NOT promise or predict algorithmic engagement, reach, or viral performance.
    """

    CRITERIA_WEIGHTS = {
        "brand_alignment": 0.15,
        "audience_relevance": 0.15,
        "platform_suitability": 0.15,
        "clarity": 0.10,
        "originality": 0.10,
        "readability": 0.10,
        "factual_reliability": 0.10,
        "cta_relevance": 0.10,
        "language_quality": 0.05,
    }

    GENERIC_PHRASES = [
        "in today's fast-paced world",
        "game changer",
        "take it to the next level",
        "unlock your potential",
        "supercharge your",
        "look no further",
        "without further ado",
        "dive right in",
        "hustle and grind",
    ]

    @classmethod
    def evaluate(
        cls,
        title: str,
        hook: str,
        body: str,
        caption: str,
        call_to_action: str,
        hashtags: list[str],
        platform: str,
        brand: BrandProfile,
        trend: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        combined_text = f"{title} {hook} {body} {caption} {call_to_action}".lower()
        issues: list[str] = []
        suggestions: list[str] = []
        scores: dict[str, int] = {}

        # 1. Brand alignment (Tone & Archetype consistency)
        brand_score = 90
        # Check forbidden topics
        for avoid in brand.topics_to_avoid:
            if avoid.lower() in combined_text:
                brand_score -= 50
                issues.append(f"Content mentions restricted topic: '{avoid}'.")
                suggestions.append(f"Remove references to '{avoid}' to adhere to brand guidelines.")

        # Check archetype alignment
        if brand.archetype == "discipline_coach":
            if any(fluff in combined_text for fluff in ["corporate synergy", "seamlessly scale", "win-win"]):
                brand_score -= 15
                issues.append("Tone contains corporate buzzwords inappropriate for a discipline brand.")
        elif brand.archetype == "commercial_retail":
            if not any(w in combined_text for w in ["benefit", "collection", "quality", "experience", "craft", "design", "comfort", "product"]):
                brand_score -= 10
                suggestions.append("Anchor copy in concrete product utility and customer benefits.")
        scores["brand_alignment"] = max(10, min(100, brand_score))

        # 2. Audience relevance
        aud_score = 85
        if brand.audience and len(brand.audience) > 3:
            # Check if audience interest keywords are reflected
            matched_interests = [term for term in brand.audience_interests if term.lower() in combined_text]
            if matched_interests:
                aud_score += 10
            elif brand.audience_interests:
                aud_score -= 5
                suggestions.append(f"Consider addressing specific audience interests like '{brand.audience_interests[0]}'.")
        scores["audience_relevance"] = max(10, min(100, aud_score))

        # 3. Platform suitability
        plat_score = 90
        p = platform.lower().strip()
        word_count = len(body.split())

        if p == "tiktok":
            if "[hook" not in body.lower() and "[scene" not in body.lower():
                plat_score -= 15
                issues.append("Missing visual and timing script structure recommended for TikTok.")
            if word_count > 300:
                plat_score -= 20
                issues.append("Script length is too long for a short-form video (exceeds 300 words).")
                suggestions.append("Shorten narration script to stay within a 30-60 second spoken pacing.")
        elif p == "instagram":
            if len(hashtags) < 3:
                plat_score -= 10
                suggestions.append("Instagram posts benefit from 5-10 targeted discovery hashtags.")
        elif p == "linkedin":
            if "\n\n" not in body:
                plat_score -= 15
                issues.append("LinkedIn posts require white space and paragraph breaks for mobile scannability.")
                suggestions.append("Break dense paragraphs into shorter 1-2 sentence digestible thoughts.")
        scores["platform_suitability"] = max(10, min(100, plat_score))

        # 4. Clarity
        clarity_score = 90
        sentences = [s.strip() for s in re.split(r'[.!?]+', body) if s.strip()]
        avg_sentence_len = sum(len(s.split()) for s in sentences) / max(1, len(sentences))
        if avg_sentence_len > 25:
            clarity_score -= 15
            issues.append("Average sentence length is high (>25 words), reducing comprehension.")
            suggestions.append("Split long compound sentences into crisp, direct statements.")
        scores["clarity"] = max(10, min(100, clarity_score))

        # 5. Originality
        orig_score = 95
        found_generic = [phrase for phrase in cls.GENERIC_PHRASES if phrase in combined_text]
        if found_generic:
            orig_score -= (len(found_generic) * 15)
            issues.append(f"Contains cliché or generic marketing phrases: {', '.join(found_generic)}.")
            suggestions.append("Replace generic filler with concrete examples and authentic voice.")
        scores["originality"] = max(10, min(100, orig_score))

        # 6. Readability
        read_score = 90
        if len(body) > 200 and "\n" not in body:
            read_score -= 20
            issues.append("Text is a single block without paragraph breaks.")
            suggestions.append("Add line breaks to improve scannability on mobile screens.")
        scores["readability"] = max(10, min(100, read_score))

        # 7. Factual reliability
        fact_score = 95
        if trend and trend.get("title"):
            sources = trend.get("sources") or []
            if not sources:
                fact_score -= 10
                issues.append("Trend topic is cited but source evidence was sparse.")
                suggestions.append("Ensure specific claims are verified against primary source reporting.")
            # Check for hallucinated statistical claims
            numbers_in_body = re.findall(r'\b\d+(?:\.\d+)?%|\$\d+', body)
            if numbers_in_body:
                # If numbers exist in body, check if they exist in trend title or description
                trend_summary = (trend.get("title", "") + " " + str(trend.get("description", ""))).lower()
                for num in numbers_in_body:
                    if num.lower() not in trend_summary:
                        fact_score -= 15
                        issues.append(f"Statistical claim '{num}' appears without direct evidence in source research.")
                        suggestions.append("Avoid inventing numbers or percentages not cited in source evidence.")
                        break
        scores["factual_reliability"] = max(10, min(100, fact_score))

        # 8. Call to action relevance
        cta_score = 90
        if not call_to_action or len(call_to_action.strip()) < 5:
            cta_score -= 50
            issues.append("Missing or empty call to action.")
            suggestions.append("Add a direct call to action matching the post objective.")
        scores["cta_relevance"] = max(10, min(100, cta_score))

        # 9. Language quality
        lang_score = 95
        # Check basic punctuation & capital start
        if hook and not hook[0].isupper():
            lang_score -= 5
        scores["language_quality"] = max(10, min(100, lang_score))

        # Compute weighted overall score (0-100)
        overall_score = sum(
            scores[criterion] * weight
            for criterion, weight in cls.CRITERIA_WEIGHTS.items()
        )
        final_score = int(round(overall_score))

        return {
            "quality_score": final_score,
            "criteria_scores": scores,
            "weights": cls.CRITERIA_WEIGHTS,
            "issues": issues,
            "suggestions": suggestions,
            "disclaimer": "Quality score evaluates structural and editorial guidelines. It does not predict engagement or virality.",
        }
