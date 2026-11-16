from __future__ import annotations

import hmac
import os
from typing import Any

from fastapi import FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field

from .research import ResearchOrchestrator
from .content_service import ContentGenerationService
from .content_variation import ContentVariationGenerator
from .content_improvement import ContentImprovementService
from .content_strategy import ContentStrategyService
from .content_quality import ContentQualityEvaluator
from .brand_context import BrandContextService
from .brand_visual_intelligence import BrandVisualIntelligenceService
from .media_provider_manager import MediaProviderManager
from .image_generation import ImageGenerationService
from .video_generation import VideoGenerationService
from .video_storyboard import VideoStoryboardService
from .media_processing import MediaProcessingService
from .media_composer import MediaComposerService
from .scheduling_recommendations import SchedulingRecommendationCoordinator

app = FastAPI(title="SocialPilot AI Trend & Content Engine", version="2.0.0")
orchestrator = ResearchOrchestrator()
content_service = ContentGenerationService()
media_provider_manager = MediaProviderManager()
image_generation_service = ImageGenerationService()
video_generation_service = VideoGenerationService()
video_storyboard_service = VideoStoryboardService()


def _verify_internal_token(authorization: str | None) -> None:
    expected = os.getenv("TREND_ENGINE_INTERNAL_TOKEN", "") or os.getenv("AI_ENGINE_INTERNAL_TOKEN", "")
    supplied = authorization.removeprefix("Bearer ").strip() if authorization else ""
    if not expected:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Internal AI engine authentication is not configured.",
        )
    if not supplied or not hmac.compare_digest(supplied, expected):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Unauthorized internal request.",
        )


class ResearchRequest(BaseModel):
    research_id: int = Field(gt=0)
    brand: dict[str, Any]
    filters: dict[str, Any] = Field(default_factory=dict)


class ContentGenerateRequest(BaseModel):
    brand: dict[str, Any]
    objective: str | None = None
    category: str | None = None
    platform: str = "linkedin"
    content_format: str = "post"
    instructions: str | None = None
    trend: dict[str, Any] | None = None
    provider: str = "internal"
    model: str | None = None
    include_variations: bool = False
    variation_count: int = 3


class ContentVariationsRequest(BaseModel):
    brand: dict[str, Any]
    base_title: str
    base_body: str
    topic: str
    platform: str = "linkedin"
    count: int = 3
    trend: dict[str, Any] | None = None


class ContentImproveRequest(BaseModel):
    draft: dict[str, Any]
    brand: dict[str, Any]
    operation: str
    target_platform: str | None = None
    target_tone: str | None = None
    custom_instructions: str | None = None
    trend: dict[str, Any] | None = None


class ContentStrategyRequest(BaseModel):
    brand: dict[str, Any]
    objective: str | None = None
    category: str | None = None
    platform: str = "linkedin"
    trend: dict[str, Any] | None = None
    instructions: str | None = None


class ContentEvaluateRequest(BaseModel):
    brand: dict[str, Any]
    title: str
    hook: str
    body: str
    caption: str = ""
    call_to_action: str = ""
    hashtags: list[str] = Field(default_factory=list)
    platform: str = "linkedin"
    trend: dict[str, Any] | None = None


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok", "service": "trend-and-content-engine"}


@app.post("/internal/v1/research")
async def research(
    payload: ResearchRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return await orchestrator.research(payload.model_dump())


@app.post("/internal/v1/content/generate")
async def generate_content(
    payload: ContentGenerateRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    try:
        data = payload.model_dump()
        brand_payload = data.pop("brand")
        return await content_service.generate(brand_payload=brand_payload, **data)
    except RuntimeError as e:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=str(e))


@app.post("/internal/v1/content/variations")
async def generate_variations(
    payload: ContentVariationsRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    brand_profile = BrandContextService.parse(payload.brand)
    variations = ContentVariationGenerator.generate_variations(
        base_title=payload.base_title,
        base_body=payload.base_body,
        topic=payload.topic,
        platform=payload.platform,
        brand=brand_profile,
        count=payload.count,
        trend=payload.trend,
    )
    return {"variations": variations}


@app.post("/internal/v1/content/improve")
async def improve_content(
    payload: ContentImproveRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    brand_profile = BrandContextService.parse(payload.brand)
    result = ContentImprovementService.improve(
        draft=payload.draft,
        operation=payload.operation,
        brand=brand_profile,
        target_platform=payload.target_platform,
        target_tone=payload.target_tone,
        custom_instructions=payload.custom_instructions,
        trend=payload.trend,
    )
    return result


@app.post("/internal/v1/content/strategy")
async def content_strategy(
    payload: ContentStrategyRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    brand_profile = BrandContextService.parse(payload.brand)
    strategy = ContentStrategyService.develop_strategy(
        brand=brand_profile,
        objective=payload.objective,
        category=payload.category,
        platform=payload.platform,
        trend=payload.trend,
        instructions=payload.instructions,
    )
    return strategy.to_dict()


@app.post("/internal/v1/content/evaluate")
async def evaluate_content(
    payload: ContentEvaluateRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    brand_profile = BrandContextService.parse(payload.brand)
    return ContentQualityEvaluator.evaluate(
        title=payload.title,
        hook=payload.hook,
        body=payload.body,
        caption=payload.caption,
        call_to_action=payload.call_to_action,
        hashtags=payload.hashtags,
        platform=payload.platform,
        brand=brand_profile,
        trend=payload.trend,
    )


# ----------------------------------------------------------------------
# Phase 7: Pydantic Request Models & Endpoints for AI Media Generation
# ----------------------------------------------------------------------


class BrandVisualProfileRequest(BaseModel):
    brand: dict[str, Any]
    visual_settings: dict[str, Any] | None = None


class ImageGenerateRequest(BaseModel):
    brand: dict[str, Any]
    visual_settings: dict[str, Any] | None = None
    objective: str = "social_post"
    visual_style: str | None = None
    platform: str | None = None
    aspect_ratio: str | None = None
    instructions: str | None = None
    provider: str | None = None
    draft: dict[str, Any] | None = None


class VideoGenerateRequest(BaseModel):
    brand: dict[str, Any]
    visual_settings: dict[str, Any] | None = None
    draft: dict[str, Any] | None = None
    video_idea: str | None = None
    objective: str = "brand_video"
    platform: str = "instagram"
    aspect_ratio: str = "9:16"
    duration: int | None = None
    provider: str | None = None


class StoryboardRequest(BaseModel):
    brand: dict[str, Any]
    visual_settings: dict[str, Any] | None = None
    draft: dict[str, Any] | None = None
    video_idea: str | None = None
    platform: str = "instagram"
    aspect_ratio: str = "9:16"
    num_scenes: int = 6


@app.get("/internal/v1/media/health")
async def media_health() -> dict[str, Any]:
    return {
        "status": "ok",
        "providers": media_provider_manager.list_all_providers(),
    }


@app.get("/internal/v1/media/providers")
async def media_providers() -> dict[str, Any]:
    providers = media_provider_manager.list_all_providers()
    return {
        "providers": providers,
        **providers,
    }


@app.get("/internal/v1/media/formats")
async def media_formats() -> dict[str, Any]:
    return MediaProcessingService.PLATFORM_FORMATS


@app.post("/internal/v1/media/brand-visual-profile")
async def generate_brand_visual_profile(
    payload: BrandVisualProfileRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return BrandVisualIntelligenceService.build_visual_profile(
        brand_payload=payload.brand,
        visual_settings=payload.visual_settings or {},
    )


@app.post("/internal/v1/media/images/generate")
async def generate_image(
    payload: ImageGenerateRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return await ImageGenerationService.generate_image(
        brand_payload=payload.brand,
        visual_settings=payload.visual_settings,
        objective=payload.objective,
        visual_style=payload.visual_style,
        platform=payload.platform,
        aspect_ratio=payload.aspect_ratio,
        instructions=payload.instructions,
        provider=payload.provider,
        draft=payload.draft,
    )


@app.post("/internal/v1/media/videos/generate")
async def generate_video(
    payload: VideoGenerateRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    visual_profile = BrandVisualIntelligenceService.build_visual_profile(
        brand_payload=payload.brand,
        visual_settings=payload.visual_settings or {},
    )
    return await VideoGenerationService.generate_video(
        brand_payload=payload.brand,
        visual_profile=visual_profile,
        draft=payload.draft,
        video_idea=payload.video_idea,
        objective=payload.objective,
        platform=payload.platform,
        aspect_ratio=payload.aspect_ratio,
        duration=payload.duration,
        provider=payload.provider,
    )


@app.post("/internal/v1/media/storyboards/generate")
async def generate_storyboard(
    payload: StoryboardRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    visual_profile = BrandVisualIntelligenceService.build_visual_profile(
        brand_payload=payload.brand,
        visual_settings=payload.visual_settings or {},
    )
    return VideoStoryboardService.generate_storyboard(
        brand_payload=payload.brand,
        visual_profile=visual_profile,
        draft=payload.draft,
        video_idea=payload.video_idea,
        platform=payload.platform,
        aspect_ratio=payload.aspect_ratio,
        num_scenes=payload.num_scenes,
    )


class MediaComposeRequest(BaseModel):
    template: str = "quote_card"
    brand_name: str
    headline: str | None = None
    quote: str | None = None
    author: str | None = None
    caption: str | None = None
    call_to_action: str | None = None
    bullets: list[str] | None = None
    primary_color: str = "#0d1b2a"
    secondary_color: str = "#1b263b"
    accent_color: str = "#e0a96d"
    background_color: str = "#071326"
    text_color: str = "#ffffff"
    primary_font: str = "Oswald, sans-serif"
    secondary_font: str = "Roboto, sans-serif"
    logo_url: str | None = None
    logo_placement: str = "bottom_right"
    aspect_ratio: str = "1:1"
    background_image_url: str | None = None


@app.post("/internal/v1/media/compose")
async def compose_media(
    payload: MediaComposeRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return MediaComposerService.compose_graphic(
        template=payload.template,
        brand_name=payload.brand_name,
        headline=payload.headline,
        quote=payload.quote,
        author=payload.author,
        caption=payload.caption,
        call_to_action=payload.call_to_action,
        bullets=payload.bullets,
        primary_color=payload.primary_color,
        secondary_color=payload.secondary_color,
        accent_color=payload.accent_color,
        background_color=payload.background_color,
        text_color=payload.text_color,
        primary_font=payload.primary_font,
        secondary_font=payload.secondary_font,
        logo_url=payload.logo_url,
        logo_placement=payload.logo_placement,
        aspect_ratio=payload.aspect_ratio,
        background_image_url=payload.background_image_url,
    )


# --------------------------------------------------------------------------
# Phase 8: AI Content Scheduling and Autopilot Agent Endpoints
# --------------------------------------------------------------------------

class SchedulingRecommendRequest(BaseModel):
    brand: dict[str, Any]
    platform: str | None = None
    timezone: str = "UTC"
    historical_records: list[dict[str, Any]] | None = None


class SchedulingBestTimesRequest(BaseModel):
    brand: dict[str, Any]
    platform: str | None = None
    timezone: str = "UTC"
    historical_records: list[dict[str, Any]] | None = None


class SchedulingDistributeRequest(BaseModel):
    brand: dict[str, Any]
    drafts: list[dict[str, Any]] = Field(default_factory=list)
    start_date: str | None = None
    days_span: int = 14
    timezone: str = "UTC"
    min_gap_hours: int = 12


class SchedulingAutopilotPlanRequest(BaseModel):
    brand: dict[str, Any]
    autopilot_settings: dict[str, Any] = Field(default_factory=dict)
    rules: list[dict[str, Any]] = Field(default_factory=list)
    pending_items: list[dict[str, Any]] = Field(default_factory=list)
    existing_scheduled_posts: list[dict[str, Any]] = Field(default_factory=list)
    target_week_start: str | None = None


class SchedulingConflictsRequest(BaseModel):
    proposed_slot: dict[str, Any]
    existing_posts: list[dict[str, Any]] = Field(default_factory=list)
    brand_settings: dict[str, Any] | None = None
    min_gap_minutes: int = 60


@app.get("/internal/v1/scheduling/health")
async def scheduling_health() -> dict[str, Any]:
    return {
        "status": "healthy",
        "service": "ai-scheduling-autopilot-agent",
        "supported_platforms": SchedulingRecommendationCoordinator.get_best_times.__name__,
    }


@app.post("/internal/v1/scheduling/recommend")
async def scheduling_recommend(
    payload: SchedulingRecommendRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return SchedulingRecommendationCoordinator.get_strategy(payload.brand)


@app.post("/internal/v1/scheduling/best-times")
async def scheduling_best_times(
    payload: SchedulingBestTimesRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    recommendations = SchedulingRecommendationCoordinator.get_best_times(
        brand_payload=payload.brand,
        platform=payload.platform,
        timezone=payload.timezone,
        historical_records=payload.historical_records,
    )
    return {"recommendations": recommendations, "count": len(recommendations)}


@app.post("/internal/v1/scheduling/distribute")
async def scheduling_distribute(
    payload: SchedulingDistributeRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return SchedulingRecommendationCoordinator.plan_distribution(
        brand_payload=payload.brand,
        drafts=payload.drafts,
        start_date=payload.start_date,
        days_span=payload.days_span,
        timezone=payload.timezone,
        min_gap_hours=payload.min_gap_hours,
    )


@app.post("/internal/v1/scheduling/autopilot/plan")
async def scheduling_autopilot_plan(
    payload: SchedulingAutopilotPlanRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return SchedulingRecommendationCoordinator.evaluate_autopilot_plan(
        brand_payload=payload.brand,
        autopilot_settings=payload.autopilot_settings,
        rules=payload.rules,
        pending_items=payload.pending_items,
        existing_scheduled_posts=payload.existing_scheduled_posts,
        target_week_start=payload.target_week_start,
    )


@app.post("/internal/v1/scheduling/conflicts")
async def scheduling_conflicts(
    payload: SchedulingConflictsRequest,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return SchedulingRecommendationCoordinator.check_conflicts(
        proposed_slot=payload.proposed_slot,
        existing_posts=payload.existing_posts,
        brand_settings=payload.brand_settings,
        min_gap_minutes=payload.min_gap_minutes,
    )


# ── Phase 9: Publishing Intelligence Routes ──────────────────────────────────
from ai_engine.publishing_intelligence import PublishingIntelligence as _PI
from ai_engine.publishing_error_classifier import PublishingErrorClassifier as _PEC
from ai_engine.platform_compatibility import PlatformCompatibility as _PC
from ai_engine.publishing_content_validator import PublishingContentValidator as _PCV
from ai_engine.publishing_optimization import PublishingOptimizer as _PO

_pub_intel   = _PI()
_err_cls     = _PEC()
_plat_compat = _PC()
_cont_val    = _PCV()
_pub_opt     = _PO()


@app.post("/internal/v1/publishing/analyze")
async def publishing_analyze(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _pub_intel.analyze_content_suitability(payload.get("platform", ""), payload.get("content", {}))


@app.post("/internal/v1/publishing/validate")
async def publishing_validate(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _cont_val.validate(payload.get("platform", ""), payload.get("content", {}))


@app.post("/internal/v1/publishing/classify-error")
async def publishing_classify_error(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _err_cls.classify_error(payload.get("error_code", ""), payload.get("error_message", ""), payload.get("platform", ""))


@app.post("/internal/v1/publishing/optimize")
async def publishing_optimize(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _pub_opt.optimize(payload.get("platform", ""), payload.get("content", {}))


@app.post("/internal/v1/publishing/compatibility")
async def publishing_compatibility(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _plat_compat.check_compatibility(
        payload.get("platform", ""), payload.get("content_type", ""),
        payload.get("media_format"), payload.get("file_size_mb", 0)
    )


# ── Phase 10: Analytics & AI Performance Optimization Routes ───────────────────
from ai_engine.analytics_intelligence import AnalyticsIntelligence as _AI

_analytics_intel = _AI()


@app.post("/internal/v1/analytics/analyze")
async def analytics_analyze(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    posts = payload.get("posts", [])
    return {
        "performance": _analytics_intel.analyze_performance(posts),
        "engagement": _analytics_intel.analyze_engagement(posts),
    }


@app.post("/internal/v1/analytics/performance")
async def analytics_performance(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _analytics_intel.analyze_performance(payload.get("posts", []))


@app.post("/internal/v1/analytics/compare")
async def analytics_compare(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    return _analytics_intel.compare_content(payload.get("posts", []))


@app.post("/internal/v1/analytics/recommendations")
async def analytics_recommendations(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    brand = payload.get("brand", {})
    posts = payload.get("posts", [])
    period_days = int(payload.get("period_days", 30))
    recs = _analytics_intel.generate_recommendations(brand, posts, period_days)
    return {
        "status": "success",
        "brand_id": brand.get("id"),
        "recommendations": recs,
        "count": len(recs),
    }


@app.post("/internal/v1/analytics/optimize-schedule")
async def analytics_optimize_schedule(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    posts = payload.get("posts", [])
    platform = payload.get("platform")
    return _analytics_intel.optimize_schedule(posts, platform)


@app.post("/internal/v1/analytics/optimize-content")
async def analytics_optimize_content(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    draft = payload.get("draft_content", {})
    posts = payload.get("historical_posts", [])
    platform = payload.get("platform", "linkedin")
    return _analytics_intel.optimize_content(draft, posts, platform)


@app.post("/internal/v1/analytics/generate-report")
async def analytics_generate_report(
    payload: dict[str, Any],
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    _verify_internal_token(authorization)
    brand_name = payload.get("brand_name", "Brand")
    metrics = payload.get("metrics", {})
    top_posts = payload.get("top_posts", [])
    period_days = int(payload.get("period_days", 30))
    return _analytics_intel.generate_report_narrative(brand_name, metrics, top_posts, period_days)




