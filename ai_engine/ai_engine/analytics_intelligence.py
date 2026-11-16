"""Analytics Intelligence Coordinator for SocialPilot AI (Phase 10)
Central intelligence engine for social media performance analysis, AI optimization,
and evidence-based strategic content recommendations.
"""
from __future__ import annotations

from typing import Any

from .performance_analyzer import PerformanceAnalyzer
from .engagement_analysis import EngagementAnalyzer
from .audience_insights_analyzer import AudienceInsightsAnalyzer
from .publishing_time_optimizer import PublishingTimeOptimizer
from .content_strategy_optimizer import ContentStrategyOptimizer
from .optimization_recommendations import OptimizationRecommendationsGenerator
from .content_performance_predictor import ContentPerformancePredictor
from .analytics_report_generator import AnalyticsReportGenerator


class AnalyticsIntelligence:
    """Central facade coordinating all Phase 10 analytics intelligence modules."""

    def __init__(self) -> None:
        self.performance_analyzer = PerformanceAnalyzer()
        self.engagement_analyzer = EngagementAnalyzer()
        self.audience_analyzer = AudienceInsightsAnalyzer()
        self.publishing_time_optimizer = PublishingTimeOptimizer()
        self.strategy_optimizer = ContentStrategyOptimizer()
        self.recommendations_generator = OptimizationRecommendationsGenerator()
        self.performance_predictor = ContentPerformancePredictor()
        self.report_generator = AnalyticsReportGenerator()

    def analyze_performance(self, posts: list[dict[str, Any]]) -> dict[str, Any]:
        """Classify posts into performance tiers with empirical statistics."""
        return self.performance_analyzer.analyze_posts(posts)

    def analyze_engagement(self, posts: list[dict[str, Any]]) -> dict[str, Any]:
        """Analyze interaction depth and passive vs active ratios."""
        return self.engagement_analyzer.analyze_engagement_quality(posts)

    def compare_content(self, posts: list[dict[str, Any]]) -> dict[str, Any]:
        """Compare formats and categories against performance."""
        return self.strategy_optimizer.compare_formats_and_categories(posts)

    def generate_recommendations(
        self,
        brand: dict[str, Any],
        posts: list[dict[str, Any]],
        period_days: int = 30
    ) -> list[dict[str, Any]]:
        """Generate evidence-backed recommendations with honest confidence classifications."""
        return self.recommendations_generator.generate_recommendations(brand, posts, period_days)

    def optimize_schedule(
        self,
        posts: list[dict[str, Any]],
        platform: str | None = None
    ) -> dict[str, Any]:
        """Derive optimal publishing windows from empirical post engagement."""
        return self.publishing_time_optimizer.optimize_publishing_times(posts, platform)

    def optimize_content(
        self,
        draft_content: dict[str, Any],
        historical_posts: list[dict[str, Any]],
        platform: str = "linkedin"
    ) -> dict[str, Any]:
        """Produce an explicitly labeled predictive estimate."""
        return self.performance_predictor.predict_content_outcome(draft_content, historical_posts, platform)

    def generate_report_narrative(
        self,
        brand_name: str,
        metrics: dict[str, Any],
        top_posts: list[dict[str, Any]],
        period_days: int = 30
    ) -> dict[str, Any]:
        """Synthesize narrative executive summary for reports."""
        return self.report_generator.generate_narrative_summary(brand_name, metrics, top_posts, period_days)
