# Trend research sources

## Provider implementation status

All five research providers are implemented under a unified, consistent connector architecture.

| Source | Type | Status | Supported access method & notes |
|---|---|---|---|
| RSS / Atom feeds | `rss` | Implemented; administrator configuration required | URLs come from server-side `TREND_RSS_FEEDS`. The engine parses public feed entries, applies time windows, deduplicates similar headlines, filters against brand terms, and stores original source URLs, titles, excerpts, and publication dates. |
| News API | `news_api` | Implemented; administrator credentials required | Connects to the official News API (`https://newsapi.org/v2/everything`). Retrieves relevant headlines, descriptions, publication dates, publishers, and source URLs. Supports brand-specific keywords and geographic filtering. Returns `configuration_required` when `NEWSAPI_API_KEY` (or legacy `NEWS_API_KEY`) is not set. |
| Reddit API | `reddit` | Implemented; administrator credentials required | Connects via official Reddit OAuth2 Client Credentials grant (`https://www.reddit.com/api/v1/access_token` and `https://oauth.reddit.com/search`). Searches public discussions and communities. Retrieves post titles, subreddit names, timestamps, permalinks, and engagement metrics (`likes`/upvotes, `comments`). Respects Reddit rate-limit headers (`x-ratelimit-*`). Returns `configuration_required` when `REDDIT_CLIENT_ID` or `REDDIT_CLIENT_SECRET` is not set. |
| YouTube Data API | `youtube` | Implemented; administrator credentials required | Integrates official Google YouTube Data API v3 (`search` and `videos` statistics). Retrieves relevant videos, titles, descriptions, publication dates, channel names, and engagement statistics (`views`, `likes`, `comments`). Distinguishes search relevance from growing popularity. Handles API quota limits (`quotaExceeded`). Returns `configuration_required` when `YOUTUBE_API_KEY` (or legacy `YOUTUBE_DATA_API_KEY`) is not set. |
| Google Trends | `google_trends` | Implemented; ready out-of-the-box for regional trends | Supported access method: official Google Trends Daily & Realtime Search Trends RSS (`https://trends.google.com/trending/rss?geo={GEO}`). Retrieves trending search queries, traffic estimates (`approx_traffic` e.g. 50,000+), publication timestamps, and cited news items (publisher, title, URL). Also supports optional `GOOGLE_TRENDS_API_KEY` or `SERPAPI_API_KEY` for deep interest-over-time query series. Direct calls to undocumented Google Trends `/trends/api/explore` endpoints are avoided in production because they violate terms, suffer severe rate limits (HTTP 429), and require CAPTCHAs. |

Never expose API keys or credentials in client applications, mobile storage, web HTML, logs, or API responses.

## Provider configuration

Configure provider credentials in the server `.env` (never commit secrets):

```dotenv
# Phase 5 Trend Intelligence Configuration
TREND_ENGINE_URL=http://127.0.0.1:8080
TREND_ENGINE_INTERNAL_TOKEN=<random-shared-secret-token>
TREND_RSS_FEEDS=https://feeds.bbci.co.uk/news/rss.xml

# Optional external provider credentials
NEWSAPI_API_KEY=your_news_api_key_here
REDDIT_CLIENT_ID=your_reddit_client_id_here
REDDIT_CLIENT_SECRET=your_reddit_client_secret_here
YOUTUBE_API_KEY=your_google_youtube_api_key_here
GOOGLE_TRENDS_API_KEY=your_google_trends_or_serpapi_key_here
```

When credentials are not supplied, the source registry and research orchestrator honestly report the provider as `configuration_required` rather than fabricating data or reporting false connection readiness.

## Unified provider architecture

Each connector in `ai_engine/ai_engine/sources/` follows the uniform interface:

1. **Configuration detection (`is_configured`)**: Validates presence of necessary credentials or feed lists.
2. **Standardized research execution (`discover(queries, days, market)`)**:
   - Queries formatted from brand terms, niche, and audience.
   - Bounded request timeouts (10 seconds default).
   - Maximum payload size limit (2 MB) to prevent denial of service.
   - Disabled redirects and loopback/internal IP rejection (`validate_feed_url`).
3. **Data normalization**: Every provider returns uniform records:
   - `source_key`: string identifier (`rss`, `news_api`, `reddit`, `youtube`, `google_trends`)
   - `source_name`: publisher, subreddit, channel, or service name
   - `title`: clean headline or post title
   - `url`: verified public URL
   - `published_at`: UTC ISO timestamp
   - `summary`: description or selftext
   - `evidence`: verifiable excerpt with engagement details
   - `metrics`: dictionary containing available metrics (`views`, `likes`, `comments`, `interest`)
4. **Error isolation**: Providers failing individually do not cause the entire research job to fail. Sourced evidence from successful providers is retained and the job is marked `partial`. Only if zero configured providers succeed does the job transition to `failed`.

## Historical trend analysis and momentum

Momentum distinguishes trend trajectories strictly through comparable historical observations:

- **Insufficient historical evidence (`null`)**: Assigned when fewer than 2 comparable observation periods exist. Never classifies a topic as growing simply because its relevance score is high.
- **Emerging**: First observed within the past 72 hours, now observed a second time with increasing citation velocity.
- **Growing**: Observed across multiple periods (span >= 2 days) with a significant increase (>= 20%) in citation volume, search interest, or engagement metrics.
- **Established**: Consistently present across 3 or more observation periods spanning 7 or more days with stable engagement.
- **Declining**: Observed in previous periods, but citations dropped by >= 25% from its historical peak.
- **Evergreen**: Sustained baseline presence across extended history (span >= 21 days) with low citation variance, representing fundamental industry topics.
- **Time-sensitive**: Concentrated publication burst (sources tightly clustered within <= 12 hours) tied to breaking news or scheduled events.

Every calculated momentum value includes a human-readable `momentum_evidence` explanation detailing the quantitative basis (observation span, citation increase, or variance).
