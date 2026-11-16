# SocialPilot AI API Documentation

This project uses the Laravel backend as the single source of truth for web and mobile auth and account data.

## Base URL

Local development:

- http://127.0.0.1:8000/api/v1

## Authentication

The API uses Laravel Sanctum.

### Register

- Method: POST
- Route: /api/v1/auth/register
- Body:
  - name: string
  - email: string
  - password: string
  - password_confirmation: string
  - terms: boolean (required)

Example:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "terms": true
  }'
```

### Login

- Method: POST
- Route: /api/v1/auth/login
- Body:
  - email
  - password

Example:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jane@example.com",
    "password": "Password123!"
  }'
```

Response includes a bearer token and user payload.

### Current user

- Method: GET
- Route: /api/v1/auth/user
- Requires: Authorization: Bearer <token>

### Logout

- Method: POST
- Route: /api/v1/auth/logout
- Requires: Authorization: Bearer <token>

### Forgot password

- Method: POST
- Route: /api/v1/auth/forgot-password
- Body:
  - email

### Reset password

- Method: POST
- Route: /api/v1/auth/reset-password
- Body:
  - token
  - email
  - password
  - password_confirmation

## Dashboard

All dashboard and notification routes below require a Sanctum-authenticated user. The optional `brand_id` query parameter is checked against that user's owned brands; a foreign brand returns HTTP 403. Invalid filters return HTTP 422. Responses use JSON `data` fields and do not include social tokens.

| Method | Route | Response |
|---|---|---|
| GET | `/api/v1/dashboard/overview` | `data`: brand, connected account, published, scheduled, draft, failed, AI-generated, and engagement totals. AI and engagement values are `null` with explicit availability flags when their source is unknown/unavailable. |
| GET | `/api/v1/dashboard/brands` | `data`: the user's brands (`id`, `name`, `industry`, `status`) for the switcher. |
| GET | `/api/v1/dashboard/content-activity` | `data`: recent draft, post, and pending-approval records; `meta.count`. Accepts `brand_id`, `per_page` (1-50), and an optional status filter. |
| GET | `/api/v1/dashboard/publishing-calendar` | `data`: genuine queued/scheduled posts with brand, platform, status, and schedule time; `meta.count`. Accepts `brand_id`, `from`, `to`, and `per_page` (1-50); defaults to now through 30 days. |
| GET | `/api/v1/dashboard/social-accounts` | `data`: owner-scoped account metadata (never credentials); `providers`: provider configuration/readiness. Accepts `brand_id`. |
| GET | `/api/v1/dashboard/agent-activity` | `data`: the eight supported agent keys and latest persisted execution status, or `waiting_for_configuration`. Accepts `brand_id`. |
| GET | `/api/v1/notifications` | Paginated `data` of the authenticated user's notifications and `meta`. Accepts `per_page` (1-50), `unread_only`, and owner-validated `brand_id`. |
| POST | `/api/v1/notifications/{uuid}/read` | `data` for the caller's notification after persisting `read_at`; another user's notification returns 404. |

Example brand filter: `GET /api/v1/dashboard/overview?brand_id=12`. Manual dashboard refresh is client-driven; the app makes one request per dashboard resource and does not poll continuously.

Database notifications are currently emitted for successful brand creation, AI-generation completion (including an explicitly described internal fallback), and social OAuth connection/disconnection or authorization failure. Publishing-failure reminders, scheduled-post reminders, and autopilot notifications are not emitted until those workflows produce supported events.

## Brand endpoints

Authenticated requests may use the following routes:

- GET /api/v1/brands
- POST /api/v1/brands
- GET /api/v1/brands/{brand}
- PUT /api/v1/brands/{brand}
- DELETE /api/v1/brands/{brand}
- GET /api/v1/brands/{brand}/overview
- GET /api/v1/brands/{brand}/settings
- PUT /api/v1/brands/{brand}/settings
- PATCH /api/v1/brands/{brand}/status
- POST /api/v1/brands/{brand}/activate

List requests support `search`, `status`, `industry`, `sort` (`name`, `industry`, `status`, `created_at`, or `updated_at`), `direction` (`asc` or `desc`), `per_page` (1-50), and `page`. The response includes pagination metadata. Brand writes accept industry, niche, website URL, target audience and age range, audience interests, status, logo image (multipart, up to 5 MB), and associated voice/style/personality/values/keywords/avoid-list/objective/content-preference settings. `PUT /settings` updates the same brand/settings model and returns the updated brand resource, including its settings. The activation route sets status to `active`; the status route accepts `draft`, `active`, or `paused`.

The overview returns actual counts for connected accounts, drafts, posts, and campaigns. Brand and social-account operations are scoped to the authenticated owner and require Sanctum authentication.

## Social account status and OAuth

- GET /api/v1/social-providers returns Facebook Pages, Instagram Professional, TikTok, and YouTube with an icon key and configuration status. Credentials being present does not mean that a platform has approved the app or granted requested permissions.
- POST /api/v1/brands/{brand}/social-providers/{provider}/authorize returns an authorization URL for the authenticated owner. OAuth state is random, single-use, server-side, brand/user/provider-bound, and expires after 10 minutes.
- POST /api/v1/brands/{brand}/social-accounts/connect accepts a `provider` value (`facebook`, `instagram`, `tiktok`, or `youtube`) and starts the same OAuth flow. If credentials are absent, it returns HTTP 409 with `configuration_required`.
- Provider callbacks use the exact redirect URI registered with the provider: `/social/oauth/{provider}/callback`. The callback exchanges the code server-side, discovers real accounts, stores tokens encrypted, and redirects mobile flows to `socialpilot://oauth/callback`.
- GET /api/v1/social-accounts and GET /api/v1/brands/{brand}/social-accounts return owner-scoped metadata, including provider account ID, username/channel identifier, associated brand, status, connection/sync timestamps, and token expiry (never token values). Expired tokens report `token_expired` and are excluded from connected-account counts.
- GET /api/v1/social-accounts/{socialAccount}/status returns the current safe account metadata/status. Access to another user's account returns HTTP 403.
- POST /api/v1/social-accounts/{socialAccount}/check validates the provider connection and refreshes an expired token when that provider supports refresh.
- DELETE /api/v1/social-accounts/{socialAccount} removes the local connection and encrypted token, attempts provider revocation where supported, and reports whether provider-side revocation was confirmed.
- POST /api/v1/brands/{brand}/social-accounts is a compatibility endpoint that rejects client-supplied tokens and returns HTTP 409 with `oauth_required`. A failed or unconfigured authorization does not create a social-account record.

OAuth state is stored in the configured Laravel cache (not a database table), consumed once, and expires after 10 minutes. A multi-instance production deployment must use a shared cache store.

Configure credentials in the server `.env` (never in Flutter or source control): `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI`, `INSTAGRAM_CLIENT_ID`, `INSTAGRAM_CLIENT_SECRET`, `INSTAGRAM_REDIRECT_URI`, `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`, `TIKTOK_REDIRECT_URI`, `YOUTUBE_CLIENT_ID`, `YOUTUBE_CLIENT_SECRET`, and `YOUTUBE_REDIRECT_URI`. Each redirect must exactly match the public HTTPS callback registered in that platform's developer console. For local phone testing, use a publicly reachable HTTPS development tunnel; a provider cannot call `localhost`.

The adapters request account-read scopes only. Instagram Professional discovery uses the Meta/Facebook authorization and requires that the Instagram account be linked to a Facebook Page. TikTok profile details can require TikTok app review. YouTube channel discovery uses Google OAuth read-only scope. Platform app review, account eligibility, and granted scopes remain the provider's decision; the interface does not claim publishing access. Existing unverified connections were marked `connection_error` by the profile migration and must be reconnected through OAuth.

Related routes cover content drafts, analytics, approvals, campaigns, and posts.

## Notes

- Passwords are stored using Laravel's hashed password mechanism.
- The web app and Flutter app share the same backend and database; they should not use separate user stores.
- Mobile tokens are stored in secure device storage by the Flutter app.
- The local free AI backend is Ollama and is configured separately from the web/mobile auth flow.

## Phase 5 — trend intelligence (multi-source intelligence completed)

All trend API routes below require a Sanctum bearer token and verify that the authenticated user owns the requested brand. A foreign or missing brand returns 404. Research start is limited to 6 requests per minute. Jobs are queued on Laravel's database queue; the request returns a job payload rather than waiting for source retrieval.

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/v1/brands/{brand}/trends` | Paginated topic feed. Filters: `category`, `min_relevance`, `momentum`, `source`, `geographic_market`, `from`, `to`, `saved_only`, `sort` (`opportunity`, `relevance`, `recent`), `per_page`, `page`. |
| GET | `/api/v1/brands/{brand}/trends/{topic}` | Topic details, feedback, and cited source records. |
| POST | `/api/v1/brands/{brand}/trends/{topic}/feedback` | Save `feedback` (`relevant`, `irrelevant`, `saved`, `dismissed`) and optional note. The latest feedback is used as a bounded, user/brand-specific relevance signal in later research. |
| GET | `/api/v1/brands/{brand}/trends/categories` | Categories already observed for this brand. |
| GET | `/api/v1/brands/{brand}/trends/sources` | Returns all 5 implemented providers (`rss`, `news_api`, `reddit`, `youtube`, `google_trends`) with their connection status (`ready`, `configuration_required`, `error`). |
| POST | `/api/v1/brands/{brand}/trends/research` | Queue manual research. Accepts `days` (7/30/90), `categories[]`, `geographic_market`, and `sources[]`. Returns HTTP 202 for a new job, or HTTP 200 for a matching active job. |
| GET | `/api/v1/brands/{brand}/trends/research` | Paginated research history. |
| GET | `/api/v1/brands/{brand}/trends/research/{researchJob}` | Research job and its topics. |
| GET | `/api/v1/brands/{brand}/trends/status` | Latest research job or `null`. |
| GET | `/api/v1/brands/{brand}/trend-preferences` | Stored or default manual-only research preferences. |
| PUT | `/api/v1/brands/{brand}/trend-preferences` | Set manual/daily/weekly frequency, local time/timezone, preferred sources, categories, and geographic market. |

Example:

```http
POST /api/v1/brands/12/trends/research
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{"days":30,"geographic_market":"US","categories":["educational"],"sources":["rss","google_trends"]}
```

Returned topic resources contain `relevance_score`, `relevance_reason`, `opportunity_score`, `momentum` (`emerging`, `growing`, `established`, `declining`, `evergreen`, `time_sensitive`, or `null`), `momentum_evidence`, `geographic_market`, `content_angles`, `content_angle_status`, and `sources[]` with verifiable title, source URL, published timestamp, excerpt summary, and available engagement metrics (`views`, `likes`, `comments`, `interest`).

`momentum` is strictly calculated from historical observation series across multiple research runs. Initial observations return `null` with explicit `momentum_evidence` indicating insufficient historical evidence. A topic is never marked as growing solely on the basis of a high relevance score.

All 5 providers follow the unified architecture. When credentials are unconfigured, they report `configuration_required` rather than fabricating mock data. See [AI_ENGINE_DOCUMENTATION.md](./AI_ENGINE_DOCUMENTATION.md) and [TREND_SOURCES.md](./TREND_SOURCES.md).

## Phase 6 — AI Content Generation Agent

All content endpoints require a Sanctum bearer token and enforce brand/draft ownership. Foreign or unowned resources return HTTP 403 or 404.

### Content Generation

| Method | Route | Description |
|---|---|---|
| `POST` | `/api/v1/brands/{brand}/content/generate` | Initiates content generation. Supports `sync=true` for immediate execution or queued async via `RunContentGeneration`. Accepts `platform`, `objective`, `category`, `content_format`, `trend_id`, `instructions`. |
| `GET` | `/api/v1/content/generations/{generation}` | Polls generation job status, progress percentage, error message, and result draft ID. |
| `GET` | `/api/v1/brands/{brand}/content-strategy` | Formulates recommended strategic angles, categories, and ideas based on brand context and trend research. |

### Content Library & Management

| Method | Route | Description |
|---|---|---|
| `GET` | `/api/v1/brands/{brand}/content` | Paginated drafts. Filters: `search`, `platform`, `category`, `objective`, `status`, `page`, `per_page`. |
| `GET` | `/api/v1/content/{content}` | Detailed draft view including hook, body, caption, CTA, hashtags, quality score, variations, revisions, and cited trend sources. |
| `PUT` | `/api/v1/content/{content}` | Updates draft fields. Automatically saves a `content_revisions` entry tracking the edit. |
| `DELETE` | `/api/v1/content/{content}` | Permanently deletes draft and cascading records. |

### Content Variations & Improvement

| Method | Route | Description |
|---|---|---|
| `POST` | `/api/v1/content/{content}/variations` | Generates multiple stylistic variations (`professional`, `conversational`, `bold`, `educational`, `direct`). |
| `GET` | `/api/v1/content/{content}/variations` | Lists all variations for the draft. |
| `POST` | `/api/v1/content/{content}/variations/{variation}/select` | Selects a variation as active for the draft. |
| `POST` | `/api/v1/content/{content}/improve` | Applies targeted operation: `shorten`, `expand`, `improve_hook`, `better_cta`, `add_storytelling`, `professional`, `conversational`, `adapt_platform`. |
| `GET` | `/api/v1/content/{content}/revisions` | Full revision audit history with previous content snapshots and diffs. |

### Brand Content Settings

| Method | Route | Description |
|---|---|---|
| `GET` | `/api/v1/brands/{brand}/content-settings` | Retrieves default platform, default objective, excluded keywords, and custom writing guidelines. |
| `PUT` | `/api/v1/brands/{brand}/content-settings` | Updates content generation defaults and guidelines. |

### Generation Request Example

```http
POST /api/v1/brands/5/content/generate
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{
  "platform": "linkedin",
  "objective": "brand_authority",
  "category": "educational",
  "trend_id": 12,
  "instructions": "Focus on high-performance habit stacking for executives",
  "sync": true
}
```

Response (HTTP 201):

```json
{
  "message": "Content generated successfully.",
  "data": {
    "id": 1,
    "status": "completed",
    "progress": 100,
    "result_draft_id": 42
  },
  "draft": {
    "id": 42,
    "title": "Mastering Habit Stacking for Leaders",
    "hook": "What if 15 minutes of routine could save 3 hours of wasted effort?",
    "body": "Most executives don't fail from lack of ambition...",
    "caption": "How to stack habits that actually stick.",
    "call_to_action": "Follow for daily operational frameworks.",
    "hashtags": ["#leadership", "#habits", "#productivity"],
    "quality_score": 88,
    "quality_evaluation": {
      "criteria": {
        "hook_strength": 9,
        "platform_fit": 9,
        "tone_consistency": 9,
        "actionability": 8
      },
      "issues": [],
      "suggestions": ["Consider adding a specific numerical case study."]
    }
  }
}
```

## Phase 7: AI Media Generation Endpoints

All media generation endpoints require an authenticated user (`auth:sanctum`) and enforce strict brand ownership.

### 1. Image Generation

#### Dispatch Image Generation Job
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/media/images/generate`
- **Body:**
  - `objective` (string, required): `social_post`, `promotional_poster`, `quote_card`, `product_ad`, `educational`, `brand_awareness`, `thumbnail`, etc.
  - `visual_style` (string, optional): `dark_cinematic`, `minimalist`, `corporate`, `commercial`, `educational`, `inspirational`, `editorial`.
  - `platform` (string, optional): `instagram`, `facebook`, `linkedin`, `tiktok`, `youtube`.
  - `aspect_ratio` (string, optional): `1:1`, `9:16`, `16:9`, `4:5`, `1.91:1`.
  - `title` (string, optional): Asset label.
  - `instructions` (string, optional): Prompt details and creative direction.
  - `content_draft_id` (integer, optional): Phase 6 draft ID to associate with.
- **Response (HTTP 202 Accepted):**
  ```json
  {
    "message": "Image generation job queued successfully.",
    "job": {
      "id": 1,
      "status": "queued",
      "job_type": "image_generation"
    },
    "asset": {
      "id": 1,
      "title": "Summer Launch Poster",
      "generation_status": "queued",
      "asset_type": "image",
      "platform": "instagram",
      "aspect_ratio": "1:1"
    }
  }
  ```

#### Poll Image Generation Status
- **Method:** `GET`
- **Route:** `/api/v1/media/images/{asset}/status`
- **Response (HTTP 200 OK):**
  ```json
  {
    "data": {
      "id": 1,
      "generation_status": "completed",
      "public_url": "http://127.0.0.1:8000/storage/media/images/brand_1_lion.png",
      "width": 1024,
      "height": 1024,
      "aspect_ratio": "1:1"
    }
  }
  ```

### 2. Video Generation & Storyboards

#### Dispatch Video Generation Job
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/media/videos/generate`
- **Body:**
  - `objective` (string, required): `social_storytelling`, `product_showcase`, `motivational`, `educational`.
  - `platform` (string, optional): `instagram`, `tiktok`, `youtube`.
  - `aspect_ratio` (string, optional): `9:16`, `16:9`.
  - `duration_seconds` (integer, optional): Desired clip length (e.g. 5, 10, 15).
  - `instructions` (string, optional): Motion and camera directions.

#### Generate Video Storyboard
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/media/storyboards/generate`
- **Body:**
  - `video_idea` (string, optional): Concept outline.
  - `platform` (string, optional): `instagram`, `tiktok`, `youtube`.
  - `aspect_ratio` (string, optional): `9:16`, `16:9`.
  - `num_scenes` (integer, optional): Number of scenes (default: 6).
- **Response (HTTP 201 Created):**
  ```json
  {
    "message": "Video storyboard generated successfully.",
    "storyboard": {
      "id": 1,
      "title": "5 Habits for Unstoppable Focus",
      "platform": "instagram",
      "aspect_ratio": "9:16",
      "scenes": [
        {
          "scene_number": 1,
          "description": "Opening hero silhouette emerging from darkness",
          "visual_suggestion": "Macro lens, low-key lighting with golden rim",
          "narration_text": "Discipline is not an emotion; it is an architecture.",
          "on_screen_text": "The Architecture of Discipline",
          "duration_seconds": 5,
          "transition": "fade_in"
        }
      ]
    }
  }
  ```

### 3. Media Library Endpoints

#### List Brand Media Assets
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/media`
- **Query Parameters:** `asset_type`, `platform`, `search`, `per_page`
- **Response (HTTP 200 OK):**
  ```json
  {
    "data": [
      {
        "id": 1,
        "title": "Summer Launch Poster",
        "asset_type": "image",
        "file_format": "png",
        "platform": "instagram",
        "aspect_ratio": "1:1",
        "public_url": "http://127.0.0.1:8000/storage/media/images/brand_1_lion.png",
        "generation_status": "completed"
      }
    ],
    "links": {},
    "meta": {}
  }
  ```

#### Upload Media File
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/media/upload`
- **Multipart Form:** `file` (image/video file), `asset_type`, `title` (optional), `platform` (optional).

#### Show, Update, Delete Media Asset
- `GET /api/v1/media/{asset}` — View single asset details.
- `PUT /api/v1/media/{asset}` — Update asset title/metadata.
- `DELETE /api/v1/media/{asset}` — Soft-delete media asset.

### 4. Brand Visual Settings

#### Get Brand Visual Settings
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/visual-settings`
- **Response (HTTP 200 OK):**
  ```json
  {
    "data": {
      "id": 1,
      "brand_id": 1,
      "visual_identity": "dark_cinematic",
      "primary_color": "#0d1b2a",
      "secondary_color": "#1b263b",
      "accent_color": "#e0a96d",
      "primary_font": "Oswald",
      "secondary_font": "Roboto",
      "preferred_platforms": ["instagram", "youtube"],
      "preferred_aspect_ratios": ["9:16", "1:1"]
    }
  }
  ```

#### Update Brand Visual Settings
- **Method:** `PUT`
- **Route:** `/api/v1/brands/{brand}/visual-settings`
- **Body:** `visual_identity`, `primary_color`, `secondary_color`, `accent_color`, `primary_font`, `secondary_font`, `logo_placement`, `positive_directives`, `negative_directives`.

---

## Phase 8: Scheduling & Autopilot API

All Phase 8 endpoints require Laravel Sanctum bearer token authentication and verify brand ownership.

### 1. Scheduled Posts

#### List Scheduled Posts
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/scheduled-posts`
- **Query Parameters:** `status` (optional), `platform` (optional), `search` (optional), `per_page` (default 15)
- **Response (HTTP 200 OK):**
  ```json
  {
    "data": [
      {
        "id": 1,
        "brand_id": 1,
        "title": "Weekly Strategy Teaser",
        "caption": "Exciting news dropping soon! #teaser",
        "scheduled_at": "2026-11-20T10:00:00Z",
        "timezone": "America/New_York",
        "status": "scheduled",
        "requires_approval": false,
        "is_approved": false,
        "platforms": [
          {"platform": "instagram", "status": "scheduled"}
        ]
      }
    ],
    "meta": {"current_page": 1, "last_page": 1, "total": 1}
  }
  ```

#### Create Scheduled Post
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/scheduled-posts`
- **Body:**
  - `content_draft_id`: integer (optional, Phase 6 draft reference)
  - `title`: string (required)
  - `caption`: string (optional)
  - `body`: string (optional)
  - `scheduled_at`: string (required, datetime)
  - `timezone`: string (optional, default UTC)
  - `platform`: string (optional, e.g. `instagram`)
  - `platforms`: array (optional, for multi-platform scheduled variations)
  - `requires_approval`: boolean (optional)
  - `media_asset_ids`: array of integers (optional, Phase 7 media attachments)

#### Show, Update, Delete Scheduled Post
- `GET /api/v1/scheduled-posts/{id}` — Retrieve post details.
- `PUT /api/v1/scheduled-posts/{id}` — Update post content, caption, or approval status.
- `DELETE /api/v1/scheduled-posts/{id}` — Discard scheduled post.

#### Reschedule Post
- **Method:** `POST`
- **Route:** `/api/v1/scheduled-posts/{id}/reschedule`
- **Body:** `scheduled_at` (required), `timezone` (optional).

#### Cancel Post
- **Method:** `POST`
- **Route:** `/api/v1/scheduled-posts/{id}/cancel`
- **Body:** `reason` (optional).

#### Approve Post
- **Method:** `POST`
- **Route:** `/api/v1/scheduled-posts/{id}/approve`

---

### 2. Content Calendar

#### Get Calendar Entries
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/calendar`
- **Query Parameters:** `start_date` (YYYY-MM-DD), `end_date` (YYYY-MM-DD), `platform` (optional), `status` (optional)
- **Response (HTTP 200 OK):**
  ```json
  {
    "brand_id": 1,
    "brand_name": "Apex Brand",
    "timezone": "UTC",
    "start_date": "2026-11-01",
    "end_date": "2026-11-30",
    "count": 5,
    "data": [
      {
        "id": 1,
        "title": "Post Title",
        "platform": "instagram",
        "scheduled_at": "2026-11-15T14:00:00Z",
        "status": "scheduled"
      }
    ]
  }
  ```

#### Get Calendar Summary
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/calendar/summary`
- **Query Parameters:** `month` (1-12), `year` (YYYY)

---

### 3. Publishing Queue

#### Get Brand Queue
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/publishing-queue`

#### Configure / Initialize Queue
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/publishing-queue`
- **Body:** `name`, `status` (`active` or `paused`), `posting_interval_minutes`, `timezone`.

#### Pause / Resume Queue
- `POST /api/v1/publishing-queue/{id}/pause`
- `POST /api/v1/publishing-queue/{id}/resume`

#### Reorder Queue Items
- **Method:** `POST`
- **Route:** `/api/v1/publishing-queue/{id}/reorder`
- **Body:** `ordered_item_ids` (array of integers in desired sequence).

#### Add / Remove Queue Item
- `POST /api/v1/publishing-queue/{id}/items` — Add post or draft to queue.
- `DELETE /api/v1/publishing-queue/items/{itemId}` — Remove item from queue.

---

### 4. Scheduling Intelligence & Recommendations

#### AI Best Times to Post
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/scheduling/best-times`
- **Query Parameters:** `platform` (optional, e.g. `instagram`, `facebook`, `tiktok`, `youtube`, `linkedin`)
- **Response (HTTP 200 OK):**
  ```json
  {
    "brand_id": 1,
    "platform": "instagram",
    "timezone": "UTC",
    "count": 3,
    "data": [
      {
        "day_of_week": "Tuesday",
        "suggested_time": "10:00",
        "reason": "Mid-morning peak audience engagement window.",
        "evidence_type": "heuristic",
        "confidence_score": 0.85
      }
    ]
  }
  ```

#### Detect Scheduling Conflicts
- **Method:** `POST`
- **Route:** `/api/v1/brands/{brand}/scheduling/conflicts`
- **Body:** `scheduled_at`, `platform`, `social_account_id` (optional).

---

### 5. Autopilot Agent

#### Get Autopilot Settings
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/autopilot`

#### Update Autopilot Settings
- **Method:** `PUT`
- **Route:** `/api/v1/brands/{brand}/autopilot`
- **Body:**
  - `mode`: string (`manual`, `ai_assisted`, `automatic`)
  - `posts_per_week`: integer (1-21)
  - `min_gap_hours`: integer (1-72)
  - `preferred_days`: array of day names
  - `use_ai_optimal_times`: boolean
  - `requires_approval`: boolean
  - `blackout_periods`: array of time ranges

#### Autopilot Lifecycle Controls
- `POST /api/v1/brands/{brand}/autopilot/enable`
- `POST /api/v1/brands/{brand}/autopilot/pause`
- `POST /api/v1/brands/{brand}/autopilot/resume`
- `POST /api/v1/brands/{brand}/autopilot/disable`
- `POST /api/v1/brands/{brand}/autopilot/trigger` — Trigger immediate cycle execution.

#### Autopilot Activity Log
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/autopilot/activity`
- **Query Parameters:** `per_page` (default 20)

#### Autopilot Rules CRUD
- `POST /api/v1/brands/{brand}/autopilot/rules` — Create business rule.
- `PUT /api/v1/brands/{brand}/autopilot/rules/{id}` — Update rule.
- `DELETE /api/v1/brands/{brand}/autopilot/rules/{id}` — Remove rule.

---

### 6. Scheduling Settings

#### View Brand Scheduling Settings
- **Method:** `GET`
- **Route:** `/api/v1/brands/{brand}/scheduling-settings`

#### Update Brand Scheduling Settings
- **Method:** `PUT`
- **Route:** `/api/v1/brands/{brand}/scheduling-settings`
- **Body:** `timezone`, `publishing_frequency`, `preferred_publishing_times`, `blackout_periods`.


