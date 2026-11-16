# Publishing Agent — Database Schema Reference

## 1. Table: `publishing_attempts`
| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | PK, Auto Inc | Primary Identifier |
| `scheduled_post_id` | BIGINT UNSIGNED | Nullable, FK | Reference to scheduled_posts |
| `scheduled_post_platform_id` | BIGINT UNSIGNED | Nullable, FK | Reference to scheduled_post_platforms |
| `social_account_id` | BIGINT UNSIGNED | FK | Reference to social_accounts |
| `brand_id` | BIGINT UNSIGNED | FK | Reference to brands |
| `user_id` | BIGINT UNSIGNED | FK | Reference to users |
| `platform` | VARCHAR(32) | Indexed | facebook, instagram, tiktok, youtube, linkedin |
| `status` | VARCHAR(32) | Indexed | queued, publishing, processing, published, failed, partially_published, cancelled |
| `attempt_number` | INT UNSIGNED | Default 1 | Execution attempt number |
| `idempotency_key` | VARCHAR(128) | Unique Index | Deduplication key |
| `external_post_id` | VARCHAR(255) | Nullable | Remote platform publication ID |
| `external_post_url` | TEXT | Nullable | Direct permalink to published post |
| `provider_status` | VARCHAR(64) | Nullable | Remote platform response status |
| `error_code` | VARCHAR(64) | Nullable | API error code |
| `error_message` | TEXT | Nullable | Sanitized error description |
| `request_metadata` | JSON | Nullable | Scrubbed payload details |
| `response_metadata` | JSON | Nullable | Scrubbed provider payload |
| `is_retryable` | BOOLEAN | Default 0 | Retry eligibility |
| `next_retry_at` | TIMESTAMP | Nullable | Timestamp for next scheduled retry |
| `published_at` | TIMESTAMP | Nullable | Verified publication timestamp |

## 2. Table: `oauth_states`
Stores one-time verification tokens and PKCE code verifiers for OAuth callback validation with strict 15-minute expiration.

## 3. Table: `publishing_notifications`
Stores user-facing alerts regarding publishing outcomes: `success`, `failure`, `partial`, and `token_expired`.

## 4. Table: `publishing_webhook_events`
Stores inbound webhooks for deferred or background processing from social networks.
