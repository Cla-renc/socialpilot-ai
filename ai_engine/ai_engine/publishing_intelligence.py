"""Publishing Intelligence - Phase 9 SocialPilot AI"""

PLATFORM_LIMITS = {
    "facebook":  {"text_max": 63206, "image_max_mb": 10,   "video_max_mb": 4096},
    "instagram": {"text_max": 2200,  "image_max_mb": 8,    "video_max_mb": 100},
    "tiktok":    {"text_max": 2200,  "video_max_mb": 287,  "video_max_seconds": 600},
    "youtube":   {"title_max": 100,  "description_max": 5000, "video_max_gb": 256},
    "linkedin":  {"text_max": 3000,  "image_max_mb": 10,   "video_max_mb": 5120},
}

PLATFORM_BEST_PRACTICES = {
    "facebook":  ["Use engaging questions", "Keep posts under 80 words for best reach", "Include a clear call-to-action"],
    "instagram": ["Use relevant hashtags (5-15)", "Write compelling captions", "First line is crucial – appears above 'more'"],
    "tiktok":    ["Hook viewers in first 3 seconds", "Use trending sounds when possible", "Keep descriptions concise"],
    "youtube":   ["Optimize title for search", "Use chapters in description", "Include relevant tags"],
    "linkedin":  ["Professional tone", "Share insights, not just promotions", "Tag relevant people/companies"],
}

OPTIMAL_TIMES = {
    "facebook":  {"weekday": "09:00-13:00", "weekend": "12:00-18:00", "peak_days": ["Wednesday", "Thursday", "Friday"]},
    "instagram": {"weekday": "11:00-14:00", "weekend": "09:00-11:00", "peak_days": ["Tuesday", "Wednesday", "Friday"]},
    "tiktok":    {"weekday": "19:00-21:00", "weekend": "14:00-18:00", "peak_days": ["Tuesday", "Thursday", "Friday"]},
    "youtube":   {"weekday": "14:00-16:00", "weekend": "09:00-11:00", "peak_days": ["Saturday", "Sunday"]},
    "linkedin":  {"weekday": "08:00-10:00", "weekend": None,          "peak_days": ["Tuesday", "Wednesday", "Thursday"]},
}


class PublishingIntelligence:
    def analyze_content_suitability(self, platform: str, content: dict) -> dict:
        platform = platform.lower()
        limits   = PLATFORM_LIMITS.get(platform, {})
        practices = PLATFORM_BEST_PRACTICES.get(platform, [])

        score = 100
        recommendations = []
        warnings = []

        text     = content.get("text", "") or ""
        text_max = limits.get("text_max", limits.get("title_max", 5000))

        if len(text) > text_max:
            score -= 30
            warnings.append(f"Text exceeds {platform} limit of {text_max} characters (current: {len(text)})")
        elif len(text) == 0:
            score -= 20
            warnings.append("No text content provided")
        elif len(text) < 20:
            score -= 10
            recommendations.append("Consider adding more descriptive text for better engagement")

        if platform == "instagram":
            hashtag_count = text.count("#")
            if hashtag_count == 0:
                score -= 10
                recommendations.append("Add 5-15 hashtags to improve discoverability")
            elif hashtag_count > 30:
                score -= 15
                warnings.append("Instagram allows max 30 hashtags; excess may be ignored")

        if platform in ("youtube", "tiktok") and not content.get("media_urls"):
            score -= 40
            warnings.append(f"{platform.capitalize()} requires video content")

        recommendations.extend(practices[:2])
        score = max(0, score)

        return {
            "platform":        platform,
            "score":           score,
            "grade":           "A" if score >= 80 else "B" if score >= 60 else "C" if score >= 40 else "D",
            "recommendations": recommendations,
            "warnings":        warnings,
            "suitable":        score >= 50,
        }

    def recommend_adjustments(self, platform: str, content: dict) -> list:
        analysis    = self.analyze_content_suitability(platform, content)
        adjustments = []
        for w in analysis["warnings"]:
            adjustments.append({"type": "warning",        "message": w, "priority": "high"})
        for r in analysis["recommendations"]:
            adjustments.append({"type": "recommendation", "message": r, "priority": "medium"})
        return adjustments

    def get_optimal_posting_time(self, platform: str, timezone: str = "UTC") -> dict:
        return OPTIMAL_TIMES.get(platform.lower(), {"weekday": "09:00-17:00", "weekend": "10:00-16:00", "peak_days": []})
