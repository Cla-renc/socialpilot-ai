from __future__ import annotations

from datetime import datetime, timezone
import json
import os
from typing import Any

import httpx

from .context import BrandContext, generate_queries
from .duplicate_detection import duplicate_index, normalize_title
from .momentum import calculate_momentum
from .ollama import OllamaAngleGenerator
from .scoring import analyze_relevance, rank_opportunity, tokens
from .sources.google_trends import GoogleTrendsConnector
from .sources.news_api import NewsApiConnector
from .sources.reddit import RedditConnector
from .sources.rss import RssConnector
from .sources.youtube import YouTubeConnector


class ResearchOrchestrator:
    def __init__(
        self,
        rss: RssConnector | None = None,
        news_api: NewsApiConnector | None = None,
        reddit: RedditConnector | None = None,
        youtube: YouTubeConnector | None = None,
        google_trends: GoogleTrendsConnector | None = None,
        angle_generator: OllamaAngleGenerator | None = None,
    ) -> None:
        feeds = [
            item.strip()
            for item in os.getenv("TREND_RSS_FEEDS", "").split(",")
            if item.strip()
        ]
        self.rss = rss or RssConnector(feeds)
        self.news_api = news_api or NewsApiConnector()
        self.reddit = reddit or RedditConnector()
        self.youtube = youtube or YouTubeConnector()
        self.google_trends = google_trends or GoogleTrendsConnector()
        self.angle_generator = angle_generator or OllamaAngleGenerator()

    def _get_connector(self, key: str) -> Any:
        connectors = {
            "rss": self.rss,
            "news_api": self.news_api,
            "reddit": self.reddit,
            "youtube": self.youtube,
            "google_trends": self.google_trends,
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

        # Deduplicate while preserving order
        ordered_sources = list(dict.fromkeys(selected_sources))

        source_status: list[dict[str, Any]] = []
        articles: list[dict[str, Any]] = []

        requested_market = filters.get("geographic_market") or brand.get("region")
        requested_days = int(filters.get("days", 30))
        requested_categories = set(filters.get("categories") or [])

        # Process each selected research source through unified connector interface
        for source_key in ordered_sources:
            connector = self._get_connector(source_key)
            if connector is None:
                source_status.append({
                    "source_key": source_key,
                    "source_name": source_key.replace("_", " ").title(),
                    "status": "not_implemented",
                    "message": f"Provider '{source_key}' is not recognized.",
                    "result_count": 0,
                })
                continue

            # Special case for RSS feed list configuration
            if source_key == "rss":
                if not getattr(connector, "feed_urls", None):
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
                source_name = getattr(connector, "source_name", source_key)
                source_status.append({
                    "source_key": source_key,
                    "source_name": source_name,
                    "status": "configuration_required",
                    "message": f"{source_name} is awaiting credentials or configuration.",
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
                    status = "error" if not errors else "error"
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

        # Group near-duplicate article titles into topics
        topic_groups: dict[str, dict[str, Any]] = {}
        for article in articles:
            matched = duplicate_index(article["title"], topic_groups)
            if matched is None:
                normalized = normalize_title(article["title"])
                topic_groups[normalized] = {
                    "title": article["title"],
                    "articles": [article],
                }
            else:
                topic_groups[matched]["articles"].append(article)

        # Historical observations for momentum calculation
        historical_observations = (
            brand.get("historical_topics")
            or payload.get("historical_topics")
            or []
        )
        now_dt = datetime.now(timezone.utc)

        topics = []
        for group in topic_groups.values():
            evidence_items = group["articles"]
            primary = evidence_items[0]
            summary_text = next(
                (item["summary"] for item in evidence_items if item.get("summary")),
                None,
            )
            if not self._matches_brand_terms(
                context,
                group["title"],
                summary_text or "",
            ):
                continue
            category = self._category(group["title"], context)
            if requested_categories and category not in requested_categories:
                continue

            relevance, explanation = analyze_relevance(
                context,
                group["title"],
                summary_text or "",
                primary.get("published_at"),
                requested_market,
            )
            opportunity = rank_opportunity(
                relevance,
                primary.get("published_at"),
                requested_market,
                requested_market if requested_market and requested_market.casefold() in
                f"{group['title']} {summary_text or ''}".casefold() else None,
            )

            # Historical momentum calculation:
            # Distinguishes emerging, growing, established, declining, evergreen, time-sensitive,
            # or None for insufficient historical evidence.
            # Never classifies as growing simply due to high relevance score!
            momentum, momentum_evidence = calculate_momentum(
                title=group["title"],
                current_researched_at=now_dt,
                current_sources=evidence_items,
                historical_observations=historical_observations,
            )

            angles = []
            angle_error = None
            if not self.angle_generator.enabled:
                angle_status = "disabled"
            elif not self.angle_generator.configured:
                angle_status = "configuration_required"
            else:
                try:
                    angles = await self.angle_generator.suggest(
                        group["title"],
                        [item.get("evidence") or item["title"] for item in evidence_items],
                        context.niche or context.industry,
                    )
                    angle_status = "completed" if angles else "no_suggestions"
                except (httpx.HTTPError, json.JSONDecodeError, TypeError, ValueError):
                    angle_status = "failed"
                    angle_error = "Optional Ollama angle generation failed; sourced research is still available."

            topics.append({
                "title": group["title"][:255],
                "description": summary_text,
                "summary": summary_text,
                "category": category,
                "content_angles": angles,
                "content_angle_status": angle_status,
                "content_angle_error": angle_error,
                "relevance_score": relevance,
                "relevance_reason": explanation,
                "momentum": momentum,
                "momentum_evidence": momentum_evidence,
                "geographic_market": requested_market,
                "opportunity_score": opportunity,
                "sources": [
                    {
                        **item,
                        "published_at": (
                            item["published_at"].isoformat()
                            if item.get("published_at") else None
                        ),
                    }
                    for item in evidence_items
                ],
            })

        topics.sort(
            key=lambda item: (item["opportunity_score"], item["relevance_score"]),
            reverse=True,
        )
        return {
            "topics": topics[:100],
            "sources": source_status,
            "queries": queries,
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

    @staticmethod
    def _matches_brand_terms(context: BrandContext, title: str, summary: str) -> bool:
        content_tokens = tokens(f"{title} {summary}")
        for term in context.research_terms():
            term_tokens = tokens(term)
            if term_tokens and len(content_tokens & term_tokens) >= min(2, len(term_tokens)):
                return True
        return False
