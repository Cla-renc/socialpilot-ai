"""Publishing Optimization - Phase 9 SocialPilot AI"""
from ai_engine.publishing_intelligence import PublishingIntelligence


class PublishingOptimizer:
    def __init__(self):
        self.intelligence = PublishingIntelligence()

    def optimize(self, platform: str, content: dict, context: dict = None) -> dict:
        analysis     = self.intelligence.analyze_content_suitability(platform, content)
        optimal_time = self.intelligence.get_optimal_posting_time(platform)
        adjustments  = self.intelligence.recommend_adjustments(platform, content)

        return {
            "platform":            platform,
            "content_score":       analysis["score"],
            "content_grade":       analysis["grade"],
            "is_suitable":         analysis["suitable"],
            "optimal_posting_time": optimal_time,
            "adjustments":         adjustments,
            "summary":             (
                f"Content scores {analysis['score']}/100 ({analysis['grade']}) for {platform}. "
                + (f"Best time: {optimal_time['weekday']} on weekdays." if optimal_time.get("weekday") else "")
            ),
        }

    def compare_platforms(self, platforms: list, content: dict) -> list:
        return sorted([self.optimize(p, content) for p in platforms], key=lambda x: x["content_score"], reverse=True)
