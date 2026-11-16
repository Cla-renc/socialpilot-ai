# SocialPilot AI database notes

Laravel migrations are the source of truth. This document describes the brand, social-account, and Phase 4 dashboard schema and ownership rules.

## Brands and settings

`brands.user_id` references `users.id` with cascade-on-delete and is the Phase 3 owner boundary. An index on `(user_id, status)` supports owner-scoped filtering.

| Table | Columns and relationships |
|---|---|
| `brands` | Name, description, legacy `category`, industry, niche, website, audience profile, region, language, tone, lifecycle status, logo paths, and timestamps. Audience interests are JSON. |
| `brand_settings` | Unique `brand_id` foreign key to `brands`, cascading on brand deletion. Stores voice, writing style, formats, colors, publishing preferences, restrictions, objectives, and content preferences. Publishing frequency defaults to `weekly`; timezone defaults to `UTC`. |

Brand settings are a one-to-one relationship; one brand can have multiple social accounts. `category` remains for backward compatibility, while new interfaces use `industry`.

## Social accounts and credentials

| Table | Columns and relationships |
|---|---|
| `social_accounts` | `user_id` references `users` with cascade-on-delete. Nullable `brand_id` references `brands` with null-on-delete. Stores provider, provider account ID, display name, account type, status, non-secret JSON metadata, and timestamps. |
| `social_account_tokens` | Unique `social_account_id` foreign key, cascading on social-account deletion. Stores provider, encrypted access/refresh tokens, expiry, scopes, and timestamps. |

The unique `(provider, provider_account_id)` constraint prevents the same provider account from being stored more than once. OAuth completion checks the existing record's owner and brand before reuse; it rejects accounts already associated with another user's brand or another brand. Deleting a brand preserves its social account records but sets their `brand_id` to null.

Access and refresh tokens use Laravel encrypted Eloquent casts and are hidden from model serialization. API resources return only safe account metadata and token-expiry time; token values are never returned. Provider tokens supplied by clients are rejected.

The profile migration encrypts any existing plaintext token values and changes pre-existing `connected` records to `connection_error`, because their provenance cannot be verified. Such records must be reconnected through OAuth.

## OAuth state

OAuth state is not stored in a `social_oauth_states` table. The OAuth service stores a random, user/brand/provider/mode-bound state in Laravel's cache with a 10-minute expiry, then consumes it with a single-use cache pull during callback validation. Production deployments with multiple app instances need a shared cache store.

## Other Phase 3 relationships

- `brands` has many social accounts, settings, content drafts, posts, campaigns, analytics, members, and approval requests.
- `brand_settings` and token rows cascade when their parent is deleted. Other brand-owned records follow the delete rules in their individual migrations; do not infer their behavior from this summary.
- Web and API brand/social-account reads, mutations, OAuth initiation, status checks, and disconnects enforce the authenticated user's ownership.
- OAuth callback state is cache-backed; no separate brand-social join table or OAuth-state database table is currently used.

## Phase 4 dashboard records

| Table | Columns and relationships |
|---|---|
| `notifications` | Laravel database notifications with UUID primary key, `notifiable_type`/`notifiable_id`, JSON payload in `data`, nullable `read_at`, and timestamps. Notification queries are scoped through the authenticated user's `Notifiable` relation. |
| `agent_executions` | Future-ready execution history: owner, optional brand, `agent_key`, `status`, optional message/JSON metadata, start/completion times, and timestamps. Dashboard status uses the latest persisted row; absent executions are reported as `waiting_for_configuration`. |
| `brand_content_drafts` | Nullable `is_ai_generated` provenance flag. `false` is set for internal template drafts; `true` only for content returned by the configured provider. Legacy records without reliable provenance remain unknown rather than being counted as AI-generated. |

The corrective dashboard migration safely repairs deployments where the original notifications migration is already recorded as applied. It creates missing notification/agent tables and adds the provenance field only when absent.

Dashboard queries are owner-scoped and optionally filtered by an owned brand. Engagement totals come only from existing `brand_analytics` rows. Calendar entries come only from queued/scheduled `brand_posts` with a real schedule time. Social account resources exclude access tokens, refresh tokens, secrets, and authorization values.

## Phase 5 trend intelligence schema

The trend migrations create the following tables:

| Table | Purpose and key fields |
|---|---|
| `trend_research_jobs` | Owner and brand foreign keys; `status`, normalized `criteria_hash`, JSON filters/source statuses, error message, start/completion times. Indexed for brand history and active criteria de-duplication. |
| `trending_topics` | Brand and originating research-job foreign keys; title/normalized title, source excerpt/summary, JSON content angles, category, explained relevance score, nullable momentum/evidence, geographic market, opportunity score, and research timestamp. A unique job/title key prevents duplicates in one run. |
| `trend_sources` | Connector key/name/type, connection status, last success/error and rate-limit metadata. Provider credentials are not stored in this registry. |
| `trend_topic_sources` | Topic and optional connector foreign keys; stable source key/name, title, URL, SHA-256 URL hash, publication date, excerpt/evidence, and optional source metrics. The URL hash is uniquely constrained per topic. |
| `brand_trend_preferences` | One row per brand: manual/daily/weekly frequency, local time and timezone, JSON preferred sources/categories, geographic market. Frequency defaults to `manual`. |
| `trend_feedback` | Topic/user foreign keys, latest feedback type and optional note. Unique `(trend_topic_id, user_id)` keeps the user's most recent response. Research only passes the requesting owner’s latest brand feedback to scoring. |

`trending_topics.content_angle_status` and nullable `content_angle_error` are added by a separate additive migration so existing deployments can safely record optional Ollama state. Legacy angle status defaults to `unavailable`; new topic states distinguish `disabled`, `configuration_required`, `completed`, `no_suggestions`, and `failed`. Source evidence remains persisted when optional angle generation fails.

Deleting a brand cascades its research jobs, topics, source associations, preferences, and feedback through their foreign keys. Deleting the user cascades owned jobs and feedback. The Python service has no database credentials; only the Laravel queue worker persists validated, source-backed response data.

## Phase 6 AI content generation schema

The Phase 6 migration (`2026_11_13_131818_add_content_generation_fields_and_tables.php`) creates the following tables and extends `brand_content_drafts`:

### New Tables

| Table | Purpose and key fields |
|---|---|
| `content_generation_jobs` | Tracks background generation jobs: `user_id`, `brand_id`, `status` (`pending`, `processing`, `completed`, `failed`), `progress` (0-100), `platform`, `objective`, `category`, `content_format`, `parameters` (JSON), `result_draft_id`, `error_message`, `started_at`, `completed_at`. |
| `content_variations` | Stores stylistic alternative versions: `user_id`, `brand_id`, `draft_id`, `style` (`professional`, `conversational`, `bold`, `educational`, `direct`), `hook`, `body`, `caption`, `call_to_action`, `hashtags` (JSON), `quality_score`, `is_selected` (boolean). |
| `content_revisions` | Complete audit log of edits: `draft_id`, `user_id`, `revision_number`, `action` (`initial_generation`, `user_edit`, `shorten`, `expand`, etc.), `previous_title`, `previous_body`, `previous_hook`, `new_title`, `new_body`, `new_hook`, `diff_metadata` (JSON). |
| `content_trend_sources` | Preserves relationships between generated content and Phase 5 trends: `draft_id`, `trending_topic_id`, `relevance_score_at_generation`, `trend_title`, `evidence_notes`. Unique on `(draft_id, trending_topic_id)`. |
| `content_generation_settings` | Per-brand generation defaults: `brand_id` (unique), `default_platform`, `default_objective`, `default_format`, `excluded_keywords` (JSON), `preferred_hashtags` (JSON), `writing_guidelines`, `auto_quality_check` (boolean). |

### Extended `brand_content_drafts` Table

The existing `brand_content_drafts` table was extended with:
- `hook` (text, nullable): opening hook/headline.
- `caption` (text, nullable): short platform caption or summary.
- `call_to_action` (text, nullable): target call to action.
- `content_format` (string 64, nullable): format (e.g. `text_post`, `short_script`, `long_form`).
- `objective` (string 64, nullable): marketing objective (`awareness`, `engagement`, `education`, etc.).
- `category` (string 80, nullable): content category (`educational`, `motivational`, etc.).
- `quality_score` (tinyInteger unsigned, nullable): 0-100 structural score.
- `quality_evaluation` (json, nullable): criteria breakdown, issues, suggestions.
- `generation_job_id` (foreignId to `content_generation_jobs`, nullable, set null on delete).
- `selected_variation_id` (foreignId to `content_variations`, nullable, set null on delete).

## Phase 7 AI media generation schema

The Phase 7 migration (`2026_11_13_200000_create_media_generation_tables.php`) creates the following tables:

| Table | Purpose and key fields |
|---|---|
| `media_assets` | Core media record: `user_id`, `brand_id`, `content_draft_id` (nullable FK to `brand_content_drafts`), `parent_asset_id` (nullable for adapted variations), `asset_type` (`image`, `video`, `poster`, `graphic`, `thumbnail`, `storyboard`, `uploaded`), `title`, `file_name`, `file_format`, `mime_type`, `storage_disk`, `storage_path`, `public_url`, `file_size`, `width`, `height`, `aspect_ratio` (`1:1`, `16:9`, `9:16`, `4:5`, `1.91:1`), `platform`, `provider`, `provider_model`, `provider_job_id`, `generation_prompt`, `generation_params` (TEXT/JSON), `generation_status` (`pending`, `queued`, `processing`, `completed`, `failed`), `generation_error`, `objective`, `visual_style`, `is_variation` (boolean), `metadata` (TEXT/JSON), timestamps, softDeletes. |
| `media_generation_jobs` | Lifecycle tracking for async media tasks: `user_id`, `brand_id`, `media_asset_id` (nullable), `content_draft_id` (nullable), `job_type` (`image_generation`, `video_generation`, `storyboard_generation`, `image_edit`, `image_adapt`), `provider`, `provider_model`, `provider_job_id`, `status` (`queued`, `processing`, `completed`, `failed`, `cancelled`), `progress` (0-100), `request_payload` (TEXT/JSON), `result_payload` (TEXT/JSON), `error_message`, `error_code`, `retry_count`, `max_retries` (default 3), `queued_at`, `started_at`, `completed_at`, timestamps. |
| `media_storyboards` | Video storyboard containers: `user_id`, `brand_id`, `media_asset_id` (nullable), `content_draft_id` (nullable), `title`, `concept`, `platform`, `aspect_ratio`, `estimated_duration` (seconds), `status` (`draft`, `approved`, `exported`), `metadata` (TEXT/JSON), timestamps. |
| `media_storyboard_scenes` | Individual storyboard scenes: `storyboard_id` (FK cascading on delete), `scene_number`, `description`, `visual_suggestion`, `narration_text`, `on_screen_text`, `duration_seconds` (default 5), `transition` (default `cut`), `image_asset_id` (nullable FK), `audio_suggestion`, `metadata` (TEXT/JSON), timestamps. |
| `media_visual_settings` | Per-brand visual identity profile: `brand_id` (unique FK cascading on delete), `user_id`, `visual_identity` (default `minimalist`), `primary_color`, `secondary_color`, `accent_color`, `primary_font`, `secondary_font`, `logo_placement` (default `bottom_right`), `preferred_platforms` (TEXT/JSON array), `preferred_aspect_ratios` (TEXT/JSON array), `positive_directives`, `negative_directives`, `design_preferences` (TEXT/JSON), timestamps. |

## Phase 8 AI scheduling and autopilot schema

The Phase 8 migration (`2026_11_13_230000_create_scheduling_and_autopilot_tables.php`) creates the following tables:

| Table | Purpose and key fields |
|---|---|
| `scheduled_posts` | Core scheduling record: `user_id`, `brand_id`, `content_draft_id` (nullable FK), `title`, `caption`, `body`, `hashtags` (JSON), `scheduled_at` (UTC datetime), `timezone` (default `UTC`), `status` (`draft`, `scheduled`, `queued`, `pending_approval`, `published`, `cancelled`, `failed`), `requires_approval`, `is_approved`, `approved_by`, `approved_at`, `autopilot_managed`, `autopilot_rule_id`, `cancellation_reason`, `metadata` (JSON), timestamps, softDeletes. |
| `scheduled_post_platforms` | Multi-platform adaptations: `scheduled_post_id` (FK cascading), `social_account_id` (nullable FK), `platform`, `custom_title`, `custom_caption`, `custom_body`, `custom_hashtags` (JSON), `scheduled_at` (UTC), `status`, `platform_specific_options` (JSON), timestamps. |
| `scheduled_post_media` | Media attachments linking Phase 7 assets: `scheduled_post_id` (FK cascading), `media_asset_id` (FK cascading), `order` (integer default 0), timestamps. |
| `publishing_queues` | Staging queues for automated cadence: `brand_id` (FK cascading), `user_id`, `name`, `status` (`active`, `paused`), `posting_interval_minutes` (default 180), `preferred_days` (JSON), `preferred_hours` (JSON), `timezone`, timestamps. |
| `publishing_queue_items` | Individual queue entries: `publishing_queue_id` (FK cascading), `brand_id`, `user_id`, `scheduled_post_id` (nullable FK), `content_draft_id` (nullable FK), `position` (integer), `priority` (`low`, `normal`, `high`), `status` (`queued`, `scheduled`, `processed`, `skipped`), timestamps. |
| `autopilot_settings` | Per-brand autopilot preferences: `brand_id` (unique FK cascading), `user_id`, `is_enabled` (boolean), `mode` (`manual`, `ai_assisted`, `automatic`), `posts_per_week` (default 3), `min_gap_hours` (default 12), `preferred_days` (JSON), `preferred_hours` (JSON), `preferred_platforms` (JSON), `preferred_categories` (JSON), `requires_approval` (boolean default true), `blackout_periods` (JSON), `timezone`, timestamps. |
| `autopilot_rules` | Business scheduling logic: `brand_id` (FK cascading), `user_id`, `name`, `rule_type`, `conditions` (JSON), `actions` (JSON), `priority` (integer default 10), `is_active` (boolean default true), timestamps. |
| `autopilot_activity_logs` | Immutable audit log: `brand_id` (FK cascading), `user_id`, `action`, `status` (`success`, `warning`, `error`, `info`), `details` (JSON), timestamps. |
| `scheduling_recommendations` | Cached AI Best Time to Post proposals: `brand_id` (FK cascading), `platform`, `day_of_week`, `suggested_time`, `timezone`, `evidence_type` (`heuristic`, `historical`), `confidence_score` (float), `reason`, `data_used` (JSON), `expires_at`, timestamps. |


