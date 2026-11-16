from __future__ import annotations

from datetime import datetime, timezone
import math
from typing import Any

from .duplicate_detection import normalize_title
from .scoring import tokens


MOMENTUM_TYPES = {
    "emerging",
    "growing",
    "established",
    "declining",
    "evergreen",
    "time_sensitive",
}


def _parse_ts(value: Any) -> datetime | None:
    if isinstance(value, datetime):
        return value if value.tzinfo else value.replace(tzinfo=timezone.utc)
    if isinstance(value, str):
        try:
            dt = datetime.fromisoformat(value.replace("Z", "+00:00"))
            return dt if dt.tzinfo else dt.replace(tzinfo=timezone.utc)
        except (ValueError, TypeError):
            return None
    return None


def calculate_momentum(
    title: str,
    current_researched_at: datetime,
    current_sources: list[dict[str, Any]],
    historical_observations: list[dict[str, Any]],
) -> tuple[str | None, str | None]:
    """Calculate topic momentum based strictly on comparable historical observations over time.

    Rule: Never classify a topic as growing simply because its relevance score is high.
    Requires at least 2 comparable observation periods.
    """
    normalized = normalize_title(title)
    target_tokens = tokens(normalized)

    # Find comparable past observations for the same topic
    matching_past: list[dict[str, Any]] = []
    for obs in historical_observations:
        obs_title = obs.get("title") or obs.get("normalized_title") or ""
        obs_norm = normalize_title(obs_title)
        obs_tokens = tokens(obs_norm)

        # Match exact normalized title or high token overlap
        if obs_norm == normalized or (
            target_tokens and len(target_tokens & obs_tokens) / max(len(target_tokens), 1) >= 0.7
        ):
            ts = _parse_ts(obs.get("researched_at") or obs.get("created_at"))
            if ts and ts < current_researched_at:
                matching_past.append({**obs, "_ts": ts})

    # If insufficient historical evidence exists (< 1 past observation, meaning only 1 current observation)
    if not matching_past:
        return None, "Insufficient historical evidence (requires at least 2 comparable observation periods)."

    # Sort historical observations chronologically
    matching_past.sort(key=lambda x: x["_ts"])

    # Extract time series metrics
    earliest_ts = matching_past[0]["_ts"]
    latest_past = matching_past[-1]
    latest_past_ts = latest_past["_ts"]

    span_days = max((current_researched_at - earliest_ts).total_seconds() / 86400.0, 0.1)
    gap_from_last = (current_researched_at - latest_past_ts).total_seconds() / 86400.0

    current_citations = len(current_sources)
    past_citations = [
        int(obs.get("source_count") or len(obs.get("sources") or []) or 1)
        for obs in matching_past
    ]
    latest_past_citations = past_citations[-1]
    avg_past_citations = sum(past_citations) / len(past_citations)

    # Check publication dates of current sources for time-sensitivity
    pub_dates = [
        _parse_ts(s.get("published_at"))
        for s in current_sources
        if _parse_ts(s.get("published_at")) is not None
    ]
    pub_span_hours = (
        (max(pub_dates) - min(pub_dates)).total_seconds() / 3600.0
        if len(pub_dates) >= 2
        else 0.0
    )

    # 1. Growing topics:
    # When observed across multiple days (span >= 2.0 days) and citations increased significantly (>= 20%)
    citation_increase = (
        (current_citations - latest_past_citations) / max(latest_past_citations, 1)
    )
    if span_days >= 2.0 and citation_increase >= 0.20 and current_citations > latest_past_citations:
        return (
            "growing",
            f"Observed {len(matching_past) + 1} times over {span_days:.1f} days; citation volume increased by {citation_increase * 100:.0f}% from last observation.",
        )

    # 2. Evergreen topics:
    # Long historical presence (span >= 21 days), 3+ past observations, stable citation volume
    if len(matching_past) >= 3 and span_days >= 21:
        citation_variance = sum((c - avg_past_citations) ** 2 for c in past_citations) / len(past_citations)
        std_dev = math.sqrt(citation_variance)
        if std_dev <= max(1.5, avg_past_citations * 0.35) and abs(current_citations - avg_past_citations) <= 2:
            return (
                "evergreen",
                f"Sustained presence over {span_days:.0f} days across {len(matching_past) + 1} observation points with stable baseline interest.",
            )

    # 3. Emerging topics:
    # First observed within the past 72 hours, now on second observation with sustained or increasing velocity
    if len(matching_past) == 1 and span_days <= 3.0:
        if current_citations >= latest_past_citations:
            return (
                "emerging",
                f"Newly emerged within the past {span_days * 24:.0f} hours with increasing citations ({latest_past_citations} -> {current_citations}).",
            )

    # 4. Time-sensitive topics:
    # Concentrated breaking spike (pub_span <= 12h, span <= 1.0 day) with rapid citation burst
    if len(pub_dates) >= 2 and pub_span_hours <= 12 and span_days <= 1.0 and current_citations >= 3:
        return (
            "time_sensitive",
            f"Concentrated publication spike ({len(current_sources)} sources within {pub_span_hours:.1f} hours); time-sensitive topic.",
        )

    # 4. Growing topics:
    # Citations or engagement metrics increased significantly (> 20%) compared to previous period
    citation_increase = (
        (current_citations - latest_past_citations) / max(latest_past_citations, 1)
    )
    if citation_increase >= 0.20 and current_citations > latest_past_citations:
        return (
            "growing",
            f"Observed {len(matching_past) + 1} times over {span_days:.1f} days; citation volume increased by {citation_increase * 100:.0f}% from last observation.",
        )

    # 5. Declining topics:
    # Citations dropped significantly (> 25%) compared to past peak
    peak_past_citations = max(past_citations)
    if current_citations <= peak_past_citations * 0.70 and current_citations < latest_past_citations:
        drop_pct = (peak_past_citations - current_citations) / peak_past_citations * 100
        return (
            "declining",
            f"Citations declined by {drop_pct:.0f}% from historical peak ({peak_past_citations} -> {current_citations}).",
        )

    # 6. Established topics:
    # Multi-period observation (>= 2 past observations spanning >= 7 days) with sustained volume
    if len(matching_past) >= 2 and span_days >= 7.0 and current_citations >= 2:
        return (
            "established",
            f"Consistently observed across {len(matching_past) + 1} observation points over {span_days:.1f} days with stable engagement.",
        )

    # Default fallback when comparable data exists but trends are flat
    return (
        "established" if len(matching_past) >= 2 else "emerging",
        f"Observed {len(matching_past) + 1} times across {span_days:.1f} days with steady presence.",
    )
