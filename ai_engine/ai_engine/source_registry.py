from __future__ import annotations

from dataclasses import asdict, dataclass, field
import os
from typing import Any

# Standard availability classifications:
# - operational: connector is implemented, credentials are verified, service is reachable.
# - configuration_required: connector is implemented but awaiting API credentials in environment.
# - authorization_required: requires end-user OAuth connection (e.g. Instagram Professional account).
# - approval_required: requires third-party platform application/developer review (e.g. TikTok Research API).
# - restricted: platform terms or paywalls prohibit $0 commercial trend discovery (e.g. X $100/mo, Facebook deprecated).
# - temporarily_unavailable: rate limit exceeded or upstream service outage.
# - error: connector encountered an unexpected execution failure.

AVAILABILITY_OPERATIONAL = "operational"
AVAILABILITY_CONFIG_REQUIRED = "configuration_required"
AVAILABILITY_AUTH_REQUIRED = "authorization_required"
AVAILABILITY_APPROVAL_REQUIRED = "approval_required"
AVAILABILITY_RESTRICTED = "restricted"
AVAILABILITY_TEMP_UNAVAILABLE = "temporarily_unavailable"
AVAILABILITY_ERROR = "error"


@dataclass
class SourceDescriptor:
    source_key: str
    platform: str
    name: str
    source_type: str
    availability: str
    is_operational: bool
    requires_auth: bool
    requires_paid: bool
    supported_discovery: list[str] = field(default_factory=list)
    geographic_limitations: str | None = None
    rate_limit_info: str | None = None
    freshness_characteristics: str = "realtime"
    reliability: float = 0.85
    limitation_message: str | None = None
    required_env_vars: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class SourceRegistry:
    """Unified Source Registry for all supported discovery providers.

    Single source of truth describing capability, availability, authentication requirements,
    and operational status across social platforms, search engines, and news feeds.
    """

    def __init__(self) -> None:
        self._descriptors: dict[str, SourceDescriptor] = {}
        self._register_default_sources()

    def _register_default_sources(self) -> None:
        # 1. Google Trends (Free official RSS feed)
        self.register(SourceDescriptor(
            source_key="google_trends",
            platform="google_trends",
            name="Google Trends",
            source_type="search_interest",
            availability=AVAILABILITY_OPERATIONAL,
            is_operational=True,
            requires_auth=False,
            requires_paid=False,
            supported_discovery=["daily_trends", "realtime_trends", "approx_traffic", "cited_news"],
            geographic_limitations="Country-level geo codes supported (e.g. US, GB, KE).",
            rate_limit_info="Standard Google web rate limits; backoff on HTTP 429.",
            freshness_characteristics="Daily & real-time search trends updated hourly.",
            reliability=0.95,
            limitation_message="Google Trends RSS reflects current search interest only; historical interest-over-time requires deep exploration.",
            required_env_vars=[],
        ))

        # 2. Configured RSS Feeds (Standard RSS / Atom)
        has_rss_feeds = bool(os.getenv("TREND_RSS_FEEDS", "").strip()) or True  # Defaults to BBC RSS
        self.register(SourceDescriptor(
            source_key="rss",
            platform="rss",
            name="Configured RSS feeds",
            source_type="rss",
            availability=AVAILABILITY_OPERATIONAL if has_rss_feeds else AVAILABILITY_CONFIG_REQUIRED,
            is_operational=has_rss_feeds,
            requires_auth=False,
            requires_paid=False,
            supported_discovery=["news_headlines", "editorial_summaries", "published_dates"],
            geographic_limitations="Dependent on configured feed regions.",
            rate_limit_info="Standard HTTP rate limits per publisher.",
            freshness_characteristics="Periodic publication updates (minutes to hours).",
            reliability=0.90,
            limitation_message=None if has_rss_feeds else "Configure one or more permitted feed URLs in TREND_RSS_FEEDS.",
            required_env_vars=["TREND_RSS_FEEDS"],
        ))

        # 3. YouTube Data API v3 (Official API Key)
        youtube_key = (os.getenv("YOUTUBE_API_KEY", "") or os.getenv("YOUTUBE_DATA_API_KEY", "")).strip()
        has_youtube = bool(youtube_key)
        self.register(SourceDescriptor(
            source_key="youtube",
            platform="youtube",
            name="YouTube Data API",
            source_type="video",
            availability=AVAILABILITY_OPERATIONAL if has_youtube else AVAILABILITY_CONFIG_REQUIRED,
            is_operational=has_youtube,
            requires_auth=True,
            requires_paid=False,
            supported_discovery=["video_search", "popular_charts", "view_counts", "like_counts", "comment_counts"],
            geographic_limitations="Global with countryCode support for trending charts.",
            rate_limit_info="10,000 quota units/day default free tier (search.list=100 units, videos.list=1 unit).",
            freshness_characteristics="Real-time video metadata and engagement statistics.",
            reliability=0.92,
            limitation_message=None if has_youtube else "YouTube Data API is awaiting credentials. Set YOUTUBE_API_KEY in the environment.",
            required_env_vars=["YOUTUBE_API_KEY"],
        ))

        # 4. Reddit Data API (OAuth 2.0 Client Credentials)
        reddit_id = os.getenv("REDDIT_CLIENT_ID", "").strip()
        reddit_secret = os.getenv("REDDIT_CLIENT_SECRET", "").strip()
        has_reddit = bool(reddit_id and reddit_secret)
        self.register(SourceDescriptor(
            source_key="reddit",
            platform="reddit",
            name="Reddit API",
            source_type="discussion",
            availability=AVAILABILITY_OPERATIONAL if has_reddit else AVAILABILITY_CONFIG_REQUIRED,
            is_operational=has_reddit,
            requires_auth=True,
            requires_paid=False,
            supported_discovery=["subreddit_search", "hot_discussions", "upvote_scores", "comment_counts"],
            geographic_limitations="Global with community-level targeting.",
            rate_limit_info="100 requests per minute with OAuth client_credentials grant.",
            freshness_characteristics="Real-time community submissions and discussions.",
            reliability=0.88,
            limitation_message=None if has_reddit else "Reddit API is awaiting credentials. Set REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET.",
            required_env_vars=["REDDIT_CLIENT_ID", "REDDIT_CLIENT_SECRET"],
        ))

        # 5. Instagram Platform API (Meta Graph API Hashtag Search)
        instagram_token = os.getenv("INSTAGRAM_ACCESS_TOKEN", "").strip()
        has_instagram = bool(instagram_token)
        self.register(SourceDescriptor(
            source_key="instagram",
            platform="instagram",
            name="Instagram Platform API",
            source_type="social_media",
            availability=AVAILABILITY_OPERATIONAL if has_instagram else AVAILABILITY_AUTH_REQUIRED,
            is_operational=has_instagram,
            requires_auth=True,
            requires_paid=False,
            supported_discovery=["hashtag_media_search", "top_posts", "like_counts", "comment_counts"],
            geographic_limitations="Global hashtag search.",
            rate_limit_info="Max 30 unique queried hashtags per 7-day rolling window per Instagram Professional account.",
            freshness_characteristics="Recent and top public media for queried hashtags.",
            reliability=0.80,
            limitation_message=None if has_instagram else "Instagram Graph API discovery requires a connected Instagram Professional (Business or Creator) account and Page authorization.",
            required_env_vars=["INSTAGRAM_ACCESS_TOKEN"],
        ))

        # 6. TikTok Research API (Academic-Only Gate)
        self.register(SourceDescriptor(
            source_key="tiktok",
            platform="tiktok",
            name="TikTok Research API",
            source_type="social_media",
            availability=AVAILABILITY_RESTRICTED,
            is_operational=False,
            requires_auth=True,
            requires_paid=False,
            supported_discovery=["academic_public_videos", "creator_stats"],
            geographic_limitations="Restricted by platform terms.",
            rate_limit_info="Gated by TikTok academic review process.",
            freshness_characteristics="Platform trend feeds not accessible via public commercial API.",
            reliability=0.0,
            limitation_message="TikTok Research API is restricted to approved non-profit academic researchers. General public commercial trend discovery is unavailable at $0.",
            required_env_vars=["TIKTOK_CLIENT_KEY", "TIKTOK_CLIENT_SECRET"],
        ))

        # 7. Facebook Graph API (Public Feed Deprecated)
        self.register(SourceDescriptor(
            source_key="facebook",
            platform="facebook",
            name="Facebook Graph API",
            source_type="social_media",
            availability=AVAILABILITY_RESTRICTED,
            is_operational=False,
            requires_auth=True,
            requires_paid=False,
            supported_discovery=["connected_page_insights", "page_posts"],
            geographic_limitations="Restricted to connected Pages.",
            rate_limit_info="200 calls/hour per user standard Graph API limits.",
            freshness_characteristics="Connected page updates only.",
            reliability=0.0,
            limitation_message="Facebook Public Feed API was deprecated by Meta. Public trend discovery is unavailable at $0 (connected Page insights only).",
            required_env_vars=["META_APP_ID", "META_APP_SECRET"],
        ))

        # 8. X API v2 (Paid Search Tier Restriction)
        self.register(SourceDescriptor(
            source_key="x",
            platform="x",
            name="X API v2",
            source_type="social_media",
            availability=AVAILABILITY_RESTRICTED,
            is_operational=False,
            requires_auth=True,
            requires_paid=True,
            supported_discovery=["recent_tweets", "trends_by_woeid"],
            geographic_limitations="Global with WOEID city/country support.",
            rate_limit_info="Free tier is write-only. Read endpoints require Basic ($100/mo) or Pro ($5,000/mo) tier.",
            freshness_characteristics="Real-time public tweets.",
            reliability=0.0,
            limitation_message="X API v2 requires a paid Basic subscription ($100/mo) for search/trend endpoints. Zero-cost policy active.",
            required_env_vars=["X_CLIENT_ID", "X_CLIENT_SECRET"],
        ))

        # 9. News API (Local Developer Tier Only)
        newsapi_key = (os.getenv("NEWSAPI_API_KEY", "") or os.getenv("NEWS_API_KEY", "")).strip()
        has_newsapi = bool(newsapi_key)
        self.register(SourceDescriptor(
            source_key="news_api",
            platform="news_api",
            name="News API",
            source_type="news",
            availability=AVAILABILITY_OPERATIONAL if has_newsapi else AVAILABILITY_CONFIG_REQUIRED,
            is_operational=has_newsapi,
            requires_auth=True,
            requires_paid=False,
            supported_discovery=["news_articles", "publisher_search"],
            geographic_limitations="Global news outlets in English and major languages.",
            rate_limit_info="Developer free tier: 100 requests/day, 24-hr delay, localhost only. Cloud IPs return HTTP 426.",
            freshness_characteristics="24-hour delayed news on free developer tier.",
            reliability=0.75,
            limitation_message=None if has_newsapi else "News API is awaiting credentials. Set NEWSAPI_API_KEY in the environment (developer tier: localhost only).",
            required_env_vars=["NEWSAPI_API_KEY"],
        ))

    def register(self, descriptor: SourceDescriptor) -> None:
        self._descriptors[descriptor.source_key] = descriptor

    def get(self, source_key: str) -> SourceDescriptor | None:
        return self._descriptors.get(source_key)

    def all(self) -> list[SourceDescriptor]:
        return list(self._descriptors.values())

    def get_operational(self) -> list[SourceDescriptor]:
        return [d for d in self._descriptors.values() if d.is_operational]

    def get_status_payload(self) -> list[dict[str, Any]]:
        return [
            {
                "source_key": d.source_key,
                "platform": d.platform,
                "name": d.name,
                "source_type": d.source_type,
                "availability": d.availability,
                "is_operational": d.is_operational,
                "requires_auth": d.requires_auth,
                "requires_paid": d.requires_paid,
                "limitation_message": d.limitation_message,
            }
            for d in self._descriptors.values()
        ]
