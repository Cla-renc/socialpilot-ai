# Publishing Troubleshooting & Error Recovery Guide

## 1. Common Errors & Resolution Matrix

| Error Type | Platform Error Code | Cause | Resolution Action |
| :--- | :--- | :--- | :--- |
| **Auth / Token Expired** | Meta `190`, TikTok `10101`, LinkedIn `401` | User password change, expired access token, or revoked app access. | Navigate to **Brand Social Accounts** and click **Reconnect** to re-authorize with official OAuth scopes. |
| **Rate Limit** | Meta `4`, Instagram `32`, TikTok `10006`, LinkedIn `429` | Platform API rate limit reached. | Marked `is_retryable = true`. Automated exponential backoff will retry at 2, 4, 8, 16, 32 minutes. |
| **Daily Quota Exceeded** | YouTube `quotaExceeded` | Daily 10,000 unit YouTube API quota reached. | System sets `is_retryable = true` and `next_retry_at` to the next quota reset window (midnight PST). |
| **Media Aspect Ratio** | Instagram `2207053` | Uploaded image does not meet 1:1, 4:5, or 16:9 ratio. | Marked non-retryable. AI Media validator flags content before publishing. Edit image dimensions in Media Studio. |
| **Page Permission Missing** | Meta `200` | Authenticated user lacks Page Admin or Content Creator role. | Grant `pages_manage_posts` permission in Meta Business Suite. |

## 2. Retry Policy
- Retries are only dispatched if `is_retryable` is `true`.
- Maximum automated retry attempts: 5.
- If a post succeeded on one platform but failed on another, the parent scheduled post moves to `partially_published`. Only failed platforms are retried.
