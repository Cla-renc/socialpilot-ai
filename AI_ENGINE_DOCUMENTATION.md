# Trend research engine

## Status

This is a Python/FastAPI service used by Laravel's queued trend research workflow. All five specified research providers are implemented:
1. **Configured RSS / Atom feeds** (`rss`)
2. **News API** (`news_api`)
3. **Reddit API** (`reddit`)
4. **YouTube Data API** (`youtube`)
5. **Google Trends** (`google_trends`)

When credentials for a provider are absent, the provider is honestly reported as `configuration_required`. When configured, real requests are executed, normalized, and cited.

## Request flow

1. Web or Flutter sends an authenticated, brand-scoped request to Laravel.
2. Laravel verifies brand ownership, validates filters, creates a `trend_research_jobs` row, and dispatches `RunTrendResearch` to the database queue after commit.
3. A Laravel queue worker sends the saved brand context, filters, and past historical topic observations to `POST /internal/v1/research` with the shared internal bearer token.
4. FastAPI executes configured connectors (RSS, News API, Reddit, YouTube, Google Trends), limits response sizes/timeouts, parses dates and evidence, deduplicates titles, filters against brand terms, scores relevance/opportunity, evaluates historical momentum, and optionally requests evidence-grounded Ollama content angles.
5. Laravel validates the service response and persists jobs, topics, source citations, source status, and angle-generation status. Web/mobile clients retrieve results from the same API/database.

The Python process has no Laravel user authentication and no database connection. It is not directly exposed to external clients; in Docker Compose it is private to the internal service network.

## Local development (Windows PowerShell)

From the project root, configure the database/queue as usual, and generate one random token to share between Laravel and the engine:

```powershell
python -c "import secrets; print(secrets.token_urlsafe(48))"
```

Set that value as `TREND_ENGINE_INTERNAL_TOKEN` in the Laravel `.env` and the Python process environment. Configure feed URLs in `TREND_RSS_FEEDS`.

Install and run the engine:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\ai_engine"
..\.venv\Scripts\python.exe -m pip install -r requirements.txt
$env:TREND_ENGINE_INTERNAL_TOKEN = "<same-local-random-token>"
$env:TREND_RSS_FEEDS = "https://feeds.bbci.co.uk/news/rss.xml"
..\.venv\Scripts\python.exe -m uvicorn ai_engine.main:app --host 127.0.0.1 --port 8080
```

For the Laravel environment, set:

```dotenv
TREND_ENGINE_URL=http://127.0.0.1:8080
TREND_ENGINE_INTERNAL_TOKEN=<same-local-random-token>
TREND_RSS_FEEDS=https://feeds.bbci.co.uk/news/rss.xml
```

In separate terminals, run the Laravel queue worker and scheduler:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI"
php artisan queue:work database --tries=3 --timeout=90
php artisan schedule:work
```

Manual research does not require the scheduler, but does require the queue worker and running engine.

## Configuration

| Variable | Meaning | Default |
|---|---|---|
| `TREND_ENGINE_URL` | Laravel worker's internal URL for FastAPI | unset; job reports not configured |
| `TREND_ENGINE_INTERNAL_TOKEN` | Shared bearer token checked with constant-time comparison | unset; endpoint returns 503 |
| `TREND_RSS_FEEDS` | Comma-separated public RSS/Atom feed URLs | empty |
| `NEWSAPI_API_KEY` | NewsAPI.org API key | unset; reports `configuration_required` |
| `REDDIT_CLIENT_ID` | Reddit developer app client ID | unset; reports `configuration_required` |
| `REDDIT_CLIENT_SECRET` | Reddit developer app client secret | unset; reports `configuration_required` |
| `YOUTUBE_API_KEY` | Google YouTube Data API v3 key | unset; reports `configuration_required` |
| `GOOGLE_TRENDS_API_KEY` | Optional SerpApi / Google Trends API key | unset; uses official Trends RSS feed |
| `TREND_ENABLE_OLLAMA_ANALYSIS` | Enables optional content-angle suggestions | `false` |
| `OLLAMA_BASE_URL` | Ollama server URL | `http://127.0.0.1:11434` |
| `OLLAMA_MODEL` | Installed Ollama model name | `llama3.2:3b` |

## Historical momentum evaluation

Momentum evaluates topic trajectories strictly from comparable historical observations:

- **Insufficient historical evidence (`null`)**: Returned when fewer than 2 observation periods exist.
- **Emerging**: First observed within 72h, now observed a second time with increasing velocity.
- **Growing**: Observed across multiple periods (span >= 2 days) with >= 20% increase in citations/metrics.
- **Established**: Sustained presence across >= 3 periods spanning >= 7 days with stable engagement.
- **Declining**: Citations dropped by >= 25% from past historical peak.
- **Evergreen**: Long-term presence (span >= 21 days) with low citation variance.
- **Time-sensitive**: Tightly clustered burst (sources published within <= 12 hours) tied to breaking events.

High relevance score alone never marks a topic as growing without temporal evidence.

## Tests

Run the complete Python test suite:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\ai_engine"
..\.venv\Scripts\python.exe -m unittest discover -s tests -v
```

All 25 unit and connector tests mock network boundaries and validate error handling, rate limiting, data normalization, and momentum classification.

---

# Phase 6: AI Content Generation Engine

## Overview

Phase 6 extends the Python/FastAPI service (`ai_engine`) with a modular, archetype-aware AI Content Generation suite that produces structured social media content, platform adaptations, content variations, and quality assessments.

## Modular Services

| Service | Module | Role |
|---------|--------|------|
| **BrandContextService** | `ai_engine.brand_context` | Ingests brand profile, determines archetype (`discipline_coach`, `commercial_retail`, `tech_authority`, `wellness_guide`, `financial_strategist`, `authentic_creator`), ensures brand-appropriate tone and structure. |
| **ContentStrategyService** | `ai_engine.content_strategy` | Formulates angles, objectives, and formats. Connects Phase 5 trend research to content ideas without fabricating facts. |
| **ContentIdeaGenerator** | `ai_engine.content_strategy` | Produces structured candidate concepts with rationales. |
| **PlatformAdaptationService** | `ai_engine.platform_adaptation` | Platform-specific adaptations for LinkedIn, Instagram, TikTok, YouTube, and Facebook with appropriate structural elements (e.g., hooks, hashtags, CTA formats, line spacing). |
| **ComponentGenerators** | `ai_engine.component_generators` | Dedicated generators for `HookGenerator`, `CaptionGenerator`, `CallToActionGenerator`, and `HashtagGenerator`. |
| **ContentQualityEvaluator** | `ai_engine.content_quality` | Evaluates drafts across 9 structural criteria (0-100 score). Explicitly notes that quality scores do not predict virality. |
| **ContentVariationGenerator** | `ai_engine.content_variation` | Generates distinct stylistic angles (`professional`, `conversational`, `bold`, `educational`, `direct`). |
| **ContentImprovementService** | `ai_engine.content_improvement` | Executes targeted edits (`shorten`, `expand`, `improve_hook`, `better_cta`, `add_storytelling`, `professional`, `conversational`, `punchy`, `persuasive`, `question_format`, `adapt_platform`). |
| **AIProviderManager** | `ai_engine.ai_providers` | Manages external AI providers (Ollama, OpenAI, Gemini, OpenRouter, Groq) and reports availability status. |
| **ContentGenerationService** | `ai_engine.content_service` | Orchestrates the end-to-end generation workflow. |

## Internal Endpoints

All endpoints require the shared bearer token (`AI_ENGINE_INTERNAL_TOKEN` or `TREND_ENGINE_INTERNAL_TOKEN`).

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/internal/v1/content/generate` | Generates complete draft (hook, body, caption, CTA, hashtags, quality score) |
| `POST` | `/internal/v1/content/variations` | Generates multiple stylistic variations of a draft |
| `POST` | `/internal/v1/content/improve` | Applies specific improvement operation to existing text |
| `POST` | `/internal/v1/content/strategy` | Generates content strategy angles and candidate ideas |
| `POST` | `/internal/v1/content/evaluate` | Evaluates quality score (0-100) and detects structural issues |

## Phase 8: Scheduling Intelligence & Autopilot Modules

Phase 8 integrates predictive scheduling intelligence into the Python AI engine:

| Module / Service | File | Purpose |
|------------------|------|---------|
| **BestTimeToPostService** | `ai_engine.best_time_to_post` | Platform-specific timing algorithms for Facebook, Instagram, TikTok, YouTube, LinkedIn; identifies archetype and distinguishes heuristic estimates from historical performance. |
| **SchedulingIntelligence** | `ai_engine.scheduling_intelligence` | Evaluates brand audience, category, and cadence to propose complete weekly distribution strategies. |
| **ConflictDetector** | `ai_engine.scheduling_conflict_detection` | Detects account collisions, past dates, and configured blackout periods. |
| **AutopilotStrategyEngine** | `ai_engine.autopilot_strategy` | Evaluates scheduling queues against autopilot operating modes (Manual, AI-Assisted, Automatic) enforcing min gaps and approvals. |
| **ContentDistributionEngine** | `ai_engine.content_distribution` | Balances content categories across calendar slots. |
| **PublishingScheduleBuilder** | `ai_engine.publishing_schedule` | Builds platform-specific schedule proposals. |
| **SchedulingRecommendationService** | `ai_engine.scheduling_recommendations` | Aggregates intelligence services for internal API responses. |

### Phase 8 Internal Endpoints

All internal endpoints require bearer token authentication:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/internal/v1/scheduling/recommendations` | Generates comprehensive strategy recommendations for brand profile |
| `POST` | `/internal/v1/scheduling/best-times` | Computes Best Time to Post proposals for brand & platform |
| `POST` | `/internal/v1/scheduling/conflicts` | Detects calendar and account conflicts for proposed post |
| `POST` | `/internal/v1/scheduling/autopilot-plan` | Evaluates autopilot execution plan for draft queues |
| `POST` | `/internal/v1/scheduling/distribution` | Optimizes content category distribution |
| `POST` | `/internal/v1/scheduling/schedule-proposal`| Generates multi-platform weekly schedule proposals |

## Python Tests

Run all 63 unit and integration tests (25 research + 14 content generation + 12 media generation + 12 scheduling AI):

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI"
$env:PYTHONPATH="ai_engine"; .venv\Scripts\python.exe -m unittest discover -s ai_engine/tests -v
```

