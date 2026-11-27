from __future__ import annotations

from collections import defaultdict
from datetime import datetime, timezone
from difflib import SequenceMatcher
import hashlib
from typing import Any

from .normalization import (
    clean_url,
    extract_content_tokens,
    extract_hashtags,
    normalize_text,
    normalize_trend_title,
)
from .trend_contract import TrendCluster

# Default minimum similarity threshold for clustering related titles
DEFAULT_SIMILARITY_THRESHOLD = 0.55

# Source key to platform mapping for unified cross-platform attribution
SOURCE_TO_PLATFORM_MAP = {
    "youtube": "youtube",
    "reddit": "reddit",
    "google_trends": "google_trends",
    "rss": "rss",
    "news_api": "news_api",
    "instagram": "instagram",
    "tiktok": "tiktok",
    "facebook": "facebook",
    "x": "x",
}


def _resolve_platform(source_key: str | None) -> str:
    if not source_key:
        return "web"
    cleaned = source_key.strip().casefold()
    return SOURCE_TO_PLATFORM_MAP.get(cleaned, cleaned)


def calculate_title_similarity(title_a: str, title_b: str) -> float:
    """Calculates deterministic hybrid similarity between two titles.

    Combines content-token Jaccard overlap (60%) and character sequence ratio (40%).
    Requires at least 2 content tokens to overlap, or an exact character sequence ratio >= 0.88,
    to prevent false positives from generic single-word overlaps.
    """
    norm_a = normalize_trend_title(title_a)
    norm_b = normalize_trend_title(title_b)
    if not norm_a or not norm_b:
        return 0.0
    if norm_a == norm_b:
        return 1.0

    tokens_a = set(extract_content_tokens(norm_a))
    tokens_b = set(extract_content_tokens(norm_b))

    if not tokens_a or not tokens_b:
        seq = SequenceMatcher(None, norm_a, norm_b).ratio()
        return seq if seq >= 0.85 else 0.0

    intersection = tokens_a & tokens_b
    union = tokens_a | tokens_b

    # Guard: Single-token overlap is insufficient unless titles are very short (<= 2 words each)
    if len(intersection) < 2 and (len(tokens_a) > 2 or len(tokens_b) > 2):
        seq = SequenceMatcher(None, norm_a, norm_b).ratio()
        return seq if seq >= 0.88 else 0.0

    jaccard = len(intersection) / len(union) if union else 0.0
    seq_ratio = SequenceMatcher(None, norm_a, norm_b).ratio()

    # If the intersection covers 100% of the shorter title's content words, boost similarity
    min_tokens = min(len(tokens_a), len(tokens_b))
    if min_tokens > 0 and len(intersection) >= min_tokens:
        jaccard = max(jaccard, 0.80)

    hybrid = 0.60 * jaccard + 0.40 * seq_ratio
    return round(hybrid, 4)


class TrendClusteringEngine:
    """Deterministic, bounded cross-platform trend clustering engine.

    Uses an inverted index over content tokens for sub-millisecond candidate lookup,
    preventing quadratic all-pairs comparisons while preserving exact multi-platform provenance.
    """

    def __init__(self, similarity_threshold: float = DEFAULT_SIMILARITY_THRESHOLD) -> None:
        self.similarity_threshold = similarity_threshold

    def cluster_articles(
        self,
        articles: list[dict[str, Any]],
        default_category: str = "general",
    ) -> list[TrendCluster]:
        """Clusters raw or normalized article dictionaries into cohesive TrendClusters.

        Guarantees:
        - Deterministic grouping independent of ingestion order where possible.
        - Idempotent deduplication of identical URLs within the same cluster.
        - Preservation of all original platform attribution, timestamps, and metrics.
        - Cross-platform confidence and weight calculation.
        """
        if not articles:
            return []

        clusters: list[TrendCluster] = []
        # Inverted index: content_token -> list of cluster indices
        token_index: dict[str, list[int]] = defaultdict(list)

        for article in articles:
            raw_title = (article.get("title") or "").strip()
            if not raw_title:
                continue

            clean_article_url = clean_url(article.get("url"))
            platform = _resolve_platform(article.get("source_key"))
            tokens = extract_content_tokens(raw_title)

            # Find candidate clusters via shared content tokens
            candidate_indices: set[int] = set()
            for token in tokens:
                for idx in token_index[token]:
                    candidate_indices.add(idx)

            best_match_idx: int | None = None
            best_similarity = 0.0

            for idx in candidate_indices:
                candidate_cluster = clusters[idx]
                sim = calculate_title_similarity(raw_title, candidate_cluster.cluster_title)
                if sim >= self.similarity_threshold and sim > best_similarity:
                    best_similarity = sim
                    best_match_idx = idx

            # Normalization fallback: check exact normalized title match against existing clusters
            if best_match_idx is None:
                norm_title = normalize_trend_title(raw_title)
                for idx, c in enumerate(clusters):
                    if c.normalized_title == norm_title:
                        best_match_idx = idx
                        best_similarity = 1.0
                        break

            published_at = article.get("published_at")
            if isinstance(published_at, str):
                try:
                    published_at = datetime.fromisoformat(published_at.replace("Z", "+00:00"))
                except ValueError:
                    published_at = None

            if best_match_idx is not None:
                # Merge into existing cluster
                target_cluster = clusters[best_match_idx]

                # Check URL deduplication (avoid duplicate citations of the exact same article)
                existing_urls = {
                    clean_url(item.get("url"))
                    for item in target_cluster.evidence_items
                }
                if clean_article_url not in existing_urls:
                    target_cluster.evidence_items.append(article)

                # Update participating platforms
                if platform not in target_cluster.participating_platforms:
                    target_cluster.participating_platforms.append(platform)
                    target_cluster.participating_platforms.sort()

                # Update observation timestamps
                if published_at:
                    if target_cluster.earliest_observed is None or published_at < target_cluster.earliest_observed:
                        target_cluster.earliest_observed = published_at
                    if target_cluster.latest_observed is None or published_at > target_cluster.latest_observed:
                        target_cluster.latest_observed = published_at

                # Index any newly added tokens to this cluster
                for token in tokens:
                    if best_match_idx not in token_index[token]:
                        token_index[token].append(best_match_idx)

            else:
                # Create a new cluster
                normalized = normalize_trend_title(raw_title)
                cluster_id = TrendCluster.generate_id(normalized)

                new_cluster = TrendCluster(
                    cluster_id=cluster_id,
                    cluster_title=raw_title,
                    normalized_title=normalized,
                    category=article.get("category") or default_category,
                    participating_platforms=[platform],
                    primary_platform=platform,
                    distinct_source_count=1,
                    evidence_count=1,
                    is_cross_platform=False,
                    cluster_confidence=0.70,
                    cross_platform_weight=1.0,
                    evidence_items=[article],
                    earliest_observed=published_at,
                    latest_observed=published_at,
                    summary=article.get("summary") or article.get("evidence"),
                )
                cluster_idx = len(clusters)
                clusters.append(new_cluster)

                for token in tokens:
                    token_index[token].append(cluster_idx)

        # Finalize cluster metrics, confidence, and weights
        for cluster in clusters:
            unique_sources = {
                item.get("source_key")
                for item in cluster.evidence_items
                if item.get("source_key")
            }
            cluster.distinct_source_count = max(1, len(unique_sources))
            cluster.evidence_count = len(cluster.evidence_items)
            cluster.is_cross_platform = len(cluster.participating_platforms) > 1

            # Cross-platform weighting and confidence scoring
            num_platforms = len(cluster.participating_platforms)
            if num_platforms > 2:
                cluster.cross_platform_weight = 1.30
                cluster.cluster_confidence = min(0.98, 0.75 + 0.08 * num_platforms + 0.02 * cluster.distinct_source_count)
            elif num_platforms == 2:
                cluster.cross_platform_weight = 1.15
                cluster.cluster_confidence = min(0.92, 0.72 + 0.08 * num_platforms + 0.02 * cluster.distinct_source_count)
            else:
                cluster.cross_platform_weight = 1.0
                cluster.cluster_confidence = min(0.85, 0.70 + 0.03 * min(cluster.distinct_source_count, 5))

            platforms_str = ", ".join(cluster.participating_platforms)
            if cluster.is_cross_platform:
                cluster.clustering_rationale = (
                    f"Corroborated across {num_platforms} platforms ({platforms_str}) "
                    f"with {cluster.evidence_count} distinct citations."
                )
            else:
                cluster.clustering_rationale = (
                    f"Observed on {cluster.primary_platform} with {cluster.evidence_count} citation(s)."
                )

        return clusters
