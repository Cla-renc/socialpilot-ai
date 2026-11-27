from __future__ import annotations

import os
from datetime import datetime, timezone
from typing import Any

import httpx

from .clustering import TrendClusteringEngine
from .context import BrandContext, generate_queries
from .duplicate_detection import duplicate_index, normalize_title
from .momentum import calculate_momentum
from .ollama import OllamaAngleGenerator
from .recommendation_engine import TrendRecommendationEngine
from .scoring import analyze_relevance, rank_opportunity, relevance_tier, tokens
from .source_registry import SourceRegistry
from .sources.facebook import FacebookConnector
from .sources.google_trends import GoogleTrendsConnector
from .sources.instagram import InstagramConnector
from .sources.news_api import NewsApiConnector
from .sources.reddit import RedditConnector
from .sources.rss import RssConnector, validate_feed_url
from .sources.tiktok import TikTokConnector
from .sources.x import XConnector
from .sources.youtube import YouTubeConnector

_DEFAULT_MIN_RELEVANCE = 25


class ResearchOrchestrator:
    def __init__(
        self,
        rss: RssConnector | None = None,
        news_api: NewsApiConnector | None = None,
        reddit: RedditConnector | None = None,
        youtube: YouTubeConnector | None = None,
        google_trends: GoogleTrendsConnector | None = None,
        instagram: InstagramConnector | None = None,
        tiktok: TikTokConnector | None = None,
        facebook: FacebookConnector | None = None,
        x: XConnector | None = None,
        angle_generator: OllamaAngleGenerator | None = None,
        source_registry: SourceRegistry | None = None,
        recommendation_engine: TrendRecommendationEngine | None = None,
    ) -> None:
        # Default RSS connector uses env var; will be overridden per-request if
        # the Laravel payload supplies rss_feeds in filters.
        env_feeds = [
            item.strip()
            for item in os.getenv("TREND_RSS_FEEDS", "").split(",")
            if item.strip()
        ]
        self._default_rss_feeds = env_feeds
        self.rss = rss or RssConnector(env_feeds)
        self.news_api = news_api or NewsApiConnector()
        self.reddit = reddit or RedditConnector()
        self.youtube = youtube or YouTubeConnector()
        self.google_trends = google_trends or GoogleTrendsConnector()
        self.instagram = instagram or InstagramConnector()
        self.tiktok = tiktok or TikTokConnector()
        self.facebook = facebook or FacebookConnector()
        self.x = x or XConnector()
        self.angle_generator = angle_generator or OllamaAngleGenerator()
        self.source_registry = source_registry or SourceRegistry()
        self.recommendation_engine = recommendation_engine or TrendRecommendationEngine(
            angle_generator=self.angle_generator
        )

    def _get_connector(self, key: str, request_rss_feeds: list[str] | None = None) -> Any:
        if key == "rss":
            # Prefer request-scoped feed URLs (propagated from Laravel config)
            if request_rss_feeds is not None:
                return RssConnector(request_rss_feeds)
            return self.rss
        connectors = {
            "news_api": self.news_api,
            "reddit": self.reddit,
            "youtube": self.youtube,
            "google_trends": self.google_trends,
            "instagram": self.instagram,
            "tiktok": self.tiktok,
            "facebook": self.facebook,
            "x": self.x,
        }
        return connectors.get(key)

    async def research(self, payload: dict[str, Any]) -> dict[str, Any]:
        brand = payload["brand"]
        filters = payload.get("filters") or {}
        context = BrandContext.from_payload(brand)
        queries = generate_queries(context)
        selected_sources = filters.get("sources") or []
        if not selected_sources:
            selected_sources = ["rss"]

        # Resolve and validate RSS feeds from request payload (from Laravel config),
        # fall back to environment variable default.
        raw_rss_feeds: list[str] = filters.get("rss_feeds") or []
        request_rss_feeds: list[str] | None = None
        if raw_rss_feeds:
            request_rss_feeds = [url for url in raw_rss_feeds if validate_feed_url(url)]

        min_relevance = int(filters.get("min_relevance_score", _DEFAULT_MIN_RELEVANCE))
        min_relevance = max(0, min(100, min_relevance))

        # Deduplicate while preserving order
        ordered_sources = list(dict.fromkeys(selected_sources))

        source_status: list[dict[str, Any]] = []
        articles: list[dict[str, Any]] = []

        requested_market = filters.get("geographic_market") or brand.get("region")
        requested_days = int(filters.get("days", 30))
        requested_categories = set(filters.get("categories") or [])

        # Process each selected research source
        for source_key in ordered_sources:
            connector = self._get_connector(
                source_key,
                request_rss_feeds=(request_rss_feeds if source_key == "rss" else None),
            )
            if connector is None:
                source_status.append({
                    "source_key": source_key,
                    "source_name": source_key.replace("_", " ").title(),
                    "status": "not_implemented",
                    "message": f"Provider '{source_key}' is not recognized.",
                    "result_count": 0,
                })
                continue

            # RSS-specific: check that effective feed list is non-empty
            if source_key == "rss":
                effective_feeds = getattr(connector, "feed_urls", None)
                if not effective_feeds:
                    source_status.append({
                        "source_key": "rss",
                        "source_name": getattr(connector, "source_name", "Configured RSS feeds"),
                        "status": "configuration_required",
                        "message": "Configure one or more permitted RSS feed URLs in TREND_RSS_FEEDS.",
                        "result_count": 0,
                    })
                    continue

            # Check general provider configuration
            is_configured = getattr(connector, "is_configured", True)
            if not is_configured:
                descriptor = self.source_registry.get(source_key)
                source_name = getattr(connector, "source_name", source_key)
                limitation_msg = (
                    descriptor.limitation_message
                    if descriptor and descriptor.limitation_message
                    else f"{source_name} is awaiting credentials or configuration."
                )
                source_status.append({
                    "source_key": source_key,
                    "source_name": source_name,
                    "status": "configuration_required",
                    "message": limitation_msg,
                    "result_count": 0,
                })
                continue

            # Execute discovery
            try:
                src_articles, successful_count, errors = await connector.discover(
                    queries=queries,
                    days=requested_days,
                    market=requested_market,
                )
                source_name = getattr(connector, "source_name", source_key)
                if successful_count == 0:
                    status = "error"
                    message = " ".join(errors) if errors else f"No results returned from {source_name}."
                elif errors:
                    status = "partial"
                    message = f"Some {source_name} requests completed with warnings: {' '.join(errors)}"
                else:
                    status = "success"
                    message = None

                source_status.append({
                    "source_key": source_key,
                    "source_name": source_name,
                    "status": status,
                    "message": message,
                    "result_count": len(src_articles),
                })
                articles.extend(src_articles)
            except httpx.HTTPError as error:
                source_status.append({
                    "source_key": source_key,
                    "source_name": getattr(connector, "source_name", source_key),
                    "status": "error",
                    "message": f"Provider request failed: {str(error)[:200]}",
                    "result_count": 0,
                })
            except Exception as ex:
                source_status.append({
                    "source_key": source_key,
                    "source_name": getattr(connector, "source_name", source_key),
                    "status": "error",
                    "message": f"Unexpected provider error: {str(ex)[:200]}",
                    "result_count": 0,
                })

        # Cluster articles across platforms into cohesive topic clusters
        clustering_engine = TrendClusteringEngine()
        clusters = clustering_engine.cluster_articles(articles)

        # Historical observations for momentum calculation
        historical_observations = (
            brand.get("historical_topics")
            or payload.get("historical_topics")
            or []
        )
        now_dt = datetime.now(timezone.utc)

        topics = []
        filtered_out_count = 0
        filtered_out_reasons: list[str] = []

        for cluster in clusters:
            evidence_items = cluster.evidence_items
            primary = evidence_items[0]
            summary_text = cluster.summary or next(
                (item["summary"] for item in evidence_items if item.get("summary")),
                None,
            )

            category = self._category(cluster.cluster_title, context)
            if requested_categories and category not in requested_categories:
                filtered_out_count += 1
                filtered_out_reasons.append(f"category mismatch: '{category}' not in {sorted(requested_categories)}")
                continue

            relevance, explanation = analyze_relevance(
                context,
                cluster.cluster_title,
                summary_text or "",
                primary.get("published_at"),
                requested_market,
            )
            opportunity = rank_opportunity(
                relevance,
                primary.get("published_at"),
                requested_market,
                requested_market if requested_market and requested_market.casefold() in
                f"{cluster.cluster_title} {summary_text or ''}".casefold() else None,
                source_key=primary.get("source_key", ""),
            )
            if cluster.is_cross_platform:
                opportunity = min(100, int(round(opportunity * cluster.cross_platform_weight)))

            # Apply configurable relevance threshold (replaces the old binary gate)
            if relevance < min_relevance:
                filtered_out_count += 1
                filtered_out_reasons.append(
                    f"'{cluster.cluster_title}' scored {relevance} < threshold {min_relevance}: {explanation}"
                )
                continue

            # Momentum calculation
            momentum, momentum_evidence = calculate_momentum(
                title=cluster.cluster_title,
                current_researched_at=now_dt,
                current_sources=evidence_items,
                historical_observations=historical_observations,
            )
            if cluster.is_cross_platform and cluster.clustering_rationale:
                momentum_evidence = f"{momentum_evidence} ({cluster.clustering_rationale})"

            angles = []
            angle_error = None
            if not self.angle_generator.enabled:
                angle_status = "disabled"
            elif not self.angle_generator.configured:
                angle_status = "configuration_required"
            else:
                try:
                    angles = await self.angle_generator.suggest(
                        cluster.cluster_title,
                        [item.get("evidence") or item["title"] for item in evidence_items],
                        context.niche or context.industry,
                    )
                    angle_status = "completed" if angles else "no_suggestions"
                except (httpx.HTTPError, ValueError, TypeError):
                    angle_status = "failed"
                    angle_error = "Optional Ollama angle generation failed; sourced research is still available."

            topics.append({
                "title": cluster.cluster_title[:255],
                "description": summary_text,
                "summary": summary_text,
                "category": category,
                "relevance_tier": relevance_tier(relevance),
                "content_angles": angles,
                "content_angle_status": angle_status,
                "content_angle_error": angle_error,
                "relevance_score": relevance,
                "relevance_reason": explanation,
                "momentum": momentum,
                "momentum_evidence": momentum_evidence,
                "geographic_market": requested_market,
                "opportunity_score": opportunity,
                "cluster_id": cluster.cluster_id,
                "participating_platforms": cluster.participating_platforms,
                "platform_count": len(cluster.participating_platforms),
                "is_cross_platform": cluster.is_cross_platform,
                "cluster_confidence": cluster.cluster_confidence,
                "sources": [
                    {
                        **item,
                        "published_at": (
                            item["published_at"].isoformat()
                            if isinstance(item.get("published_at"), datetime)
                            else (item["published_at"] if item.get("published_at") else None)
                        ),
                    }
                    for item in evidence_items
                ],
            })

        topics.sort(
            key=lambda item: (item["opportunity_score"], item["relevance_score"]),
            reverse=True,
        )

        recommendations = []
        for topic in topics:
            rec = self.recommendation_engine.evaluate_and_recommend(
                context=context,
                topic=topic,
                historical_topics=payload.get("brand", {}).get("historical_topics"),
            )
            if rec is not None:
                rec_dict = rec.to_dict()
                topic["recommendation"] = rec_dict
                recommendations.append(rec_dict)
            else:
                topic["recommendation"] = None

        # Determine result_state
        any_successful = any(
            s["status"] in {"success", "partial"}
            for s in source_status
        )
        all_config_required = all(
            s["status"] in {"configuration_required", "not_implemented"}
            for s in source_status
        ) if source_status else False
        all_error = all(
            s["status"] in {"error", "configuration_required", "not_implemented"}
            for s in source_status
        ) if source_status else True

        if all_config_required:
            result_state = "configuration_required"
        elif all_error and not any_successful:
            result_state = "all_sources_unavailable"
        elif not topics:
            result_state = "no_relevant_topics"
        else:
            result_state = "success"

        return {
            "topics": topics[:100],
            "recommendations": recommendations,
            "clusters": [c.to_dict() for c in clusters],
            "cross_platform_count": sum(1 for c in clusters if c.is_cross_platform),
            "sources": source_status,
            "queries": queries,
            "result_state": result_state,
            "filtered_out_count": filtered_out_count,
            "min_relevance_score": min_relevance,
            "momentum_available": any(t["momentum"] is not None for t in topics),
            "researched_at": now_dt.isoformat(),
        }

    @staticmethod
    def _category(title: str, context: BrandContext) -> str:
        value = title.casefold()
        category_terms = {
            "educational": ("how to", "guide", "research", "study", "explained"),
            "community": ("community", "local", "people", "customer"),
            "promotional": ("launch", "sale", "product", "brand"),
            "entertaining": ("viral", "meme", "celebrity", "entertainment"),
            "inspirational": ("story", "achievement", "success", "inspiration"),
        }
        for category in context.content_categories:
            for term in category_terms.get(category, ()):
                if term in value:
                    return category
        for category, terms in category_terms.items():
            if any(term in value for term in terms):
                return category
        return "general"
