# Social Media Publishing Agent — Architecture & Operational Documentation

## 1. Overview
The Live Social Media Publishing Agent coordinates content publishing across official platform APIs while upholding strict idempotency, platform compliance, multi-brand ownership boundaries, and token confidentiality.

## 2. Publishing Pipeline Lifecycle

```
[ Scheduled Post / Publish Now Request ]
                    │
                    ▼
       PublishingValidationService
         (Account & Content Pre-check)
                    │
                    ▼
          PublishingManager
     (Generates Idempotency Key)
                    │
                    ▼
        PublishImmediatePostJob
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
  Synchronous API         Asynchronous API
  (FB, LinkedIn)       (Instagram Container,
        │               TikTok, YouTube)
        ▼                       │
    Published                   ▼
                         CheckPublishingStatusJob
                         (Polls until Published / Failed)
```

## 3. Provider Architecture
All providers adhere to `SocialPlatformProviderInterface`:
- `FacebookPublishingProvider`: Graph API v19+ for Page Posts (Text, Image, Video).
- `InstagramPublishingProvider`: 2-step media container creation (`/media`) followed by container publication (`/media_publish`).
- `TikTokPublishingProvider`: Creator Info validation (`/v2/post/publish/creator_info/query/`) and content initialization.
- `YouTubePublishingProvider`: Resumable media upload protocol (`/upload/youtube/v3/videos?uploadType=resumable`).
- `LinkedInPublishingProvider`: UGC / Community Posts (`/v2/ugcPosts`) with Asset API registration (`/v2/assets?action=registerUpload`).

## 4. Idempotency & Retry Guarantees
- Idempotency keys are computed as: `hash('sha256', "{scheduled_post_id}:{social_account_id}:{platform}:{attempt_number}")`.
- Existing successful attempts reject duplicate executions.
- Retries increment the attempt counter, use a distinct deduplicated key, and apply exponential backoff.
- Multi-platform publications calculate partial completion status (`partially_published`) when mixed results occur.
