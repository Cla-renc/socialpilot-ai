from __future__ import annotations

from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
import hashlib
from typing import Any


@dataclass
class SourceMetrics:
    views: int | None = None
    likes: int | None = None
    comments: int | None = None
    shares: int | None = None
    score: int | None = None  # e.g. upvotes on Reddit
    interest: int | None = None  # e.g. search traffic volume on Google Trends

    def to_dict(self) -> dict[str, Any]:
        return {k: v for k, v in asdict(self).items() if v is not None}


@dataclass
class SourceEvidence:
    source_key: str
    source_name: str
    platform: str
    title: str
    url: str
    published_at: datetime | None = None
    summary: str | None = None
    evidence: str | None = None
    metrics: dict[str, Any] = field(default_factory=dict)
    author: str | None = None

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        if self.published_at:
            result["published_at"] = self.published_at.isoformat()
        return result


@dataclass
class CanonicalTrend:
    trend_id: str
    title: str
    normalized_title: str
    category: str
    platform: str
    source_key: str
    url: str
    keywords: list[str] = field(default_factory=list)
    entities: list[str] = field(default_factory=list)
    hashtags: list[str] = field(default_factory=list)
    evidence: list[SourceEvidence] = field(default_factory=list)
    published_at: datetime | None = None
    discovered_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))

    @classmethod
    def generate_id(cls, platform: str, url: str) -> str:
        digest = hashlib.sha256(f"{platform}:{url}".encode("utf-8")).hexdigest()[:16]
        return f"tr_{platform}_{digest}"

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        if self.published_at:
            result["published_at"] = self.published_at.isoformat()
        if self.discovered_at:
            result["discovered_at"] = self.discovered_at.isoformat()
        return result


@dataclass
class TrendCluster:
    cluster_id: str
    cluster_title: str
    normalized_title: str
    category: str
    participating_platforms: list[str]
    primary_platform: str
    distinct_source_count: int
    evidence_count: int
    is_cross_platform: bool
    cluster_confidence: float
    cross_platform_weight: float
    evidence_items: list[dict[str, Any]] = field(default_factory=list)
    earliest_observed: datetime | None = None
    latest_observed: datetime | None = None
    summary: str | None = None
    clustering_rationale: str = ""

    @classmethod
    def generate_id(cls, normalized_title: str) -> str:
        digest = hashlib.sha256(normalized_title.encode("utf-8")).hexdigest()[:16]
        return f"cl_{digest}"

    def to_dict(self) -> dict[str, Any]:
        return {
            "cluster_id": self.cluster_id,
            "cluster_title": self.cluster_title,
            "normalized_title": self.normalized_title,
            "category": self.category,
            "participating_platforms": self.participating_platforms,
            "primary_platform": self.primary_platform,
            "distinct_source_count": self.distinct_source_count,
            "evidence_count": self.evidence_count,
            "is_cross_platform": self.is_cross_platform,
            "cluster_confidence": round(self.cluster_confidence, 2),
            "cross_platform_weight": round(self.cross_platform_weight, 2),
            "earliest_observed": self.earliest_observed.isoformat() if self.earliest_observed else None,
            "latest_observed": self.latest_observed.isoformat() if self.latest_observed else None,
            "summary": self.summary,
            "clustering_rationale": self.clustering_rationale,
            "evidence_count": len(self.evidence_items),
        }
