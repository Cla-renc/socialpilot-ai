# OAuth 2.0 Integration & Credential Security Guide

## 1. Principles of Security
1. **Zero Client Token Exposure**: Access tokens and refresh tokens are strictly encrypted at rest via Laravel's native encrypter (`APP_KEY`) and are never exposed in JSON responses, logs, Flutter storage, or Blade JavaScript.
2. **One-Time State Hashes**: Every OAuth initiation produces a cryptographically secure entry in `oauth_states` with a 15-minute TTL, bound to the initiating user and brand.
3. **PKCE Enforcement**: Providers supporting Proof Key for Code Exchange (TikTok, Google/YouTube) use code verifier storage in `oauth_states`.

## 2. Platform Scopes Matrix

| Platform | Minimum Scopes Required |
| :--- | :--- |
| **Facebook** | `pages_manage_posts`, `pages_read_engagement`, `pages_show_list` |
| **Instagram** | `instagram_basic`, `instagram_content_publish`, `pages_show_list` |
| **TikTok** | `user.info.basic`, `video.publish`, `video.upload` |
| **YouTube** | `https://www.googleapis.com/auth/youtube.upload`, `https://www.googleapis.com/auth/youtube.readonly` |
| **LinkedIn** | `openid`, `profile`, `w_member_social`, `rw_organization_admin` |

## 3. Token Refresh Mechanism
The scheduled command `php artisan publishing:refresh-tokens` executes hourly, identifying tokens expiring within 24 hours. The corresponding provider triggers token renewal and updates the encrypted credentials in `social_account_tokens`.
