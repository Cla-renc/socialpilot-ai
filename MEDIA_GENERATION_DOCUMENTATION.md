# Phase 7: AI Media Generation Agent Documentation

## 1. Executive Summary & Architecture Overview

Phase 7 introduces the **AI Media Generation Agent** to SocialPilot AI, transforming text content and marketing objectives from Phase 6 into production-ready visual media assets (AI-generated images, quote cards, posters, platform-adapted graphics, video storyboards, and short-form videos).

### Architecture Topology

```
┌─────────────────────────────────────────────────────────────────┐
│                      Client Interfaces                          │
│   Web Application (Blade UI)      Mobile App (Flutter / Dart)   │
└────────────────┬────────────────────────────────┬───────────────┘
                 │                                │
                 ▼                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                  Laravel Backend (REST API)                     │
│  - Authentication & Brand Ownership (Sanctum)                   │
│  - Media Asset Persistence & Media Library Service             │
│  - Job Orchestration (Database Queue / Async Processors)        │
│  - File Uploads & Local / Cloud Storage Management              │
└────────────────────────────────┬────────────────────────────────┘
                                 │ Internal HTTP (HMAC / Bearer Token)
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Python / FastAPI AI Engine                    │
│  - Brand Visual Intelligence Service (Visual Profiles)          │
│  - Multi-Provider Image Generation Manager                      │
│    (OpenAI DALL-E 3, Stability AI, Gemini Imagen)               │
│  - Multi-Provider Video Generation Manager                      │
│    (Runway, Stability Video, Pika)                              │
│  - Video Storyboard & Scene Director                            │
│  - Media Processing & Platform Aspect Ratio Engine              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Brand Visual Intelligence

The **Brand Visual Intelligence Service** constructs deterministic, structured visual profiles for brands before generating any media.

### Visual Identities & Archetypes
The service maps brands dynamically into one of 8 primary visual styles:
1. **Dark Cinematic** (`dark_cinematic`): High contrast, deep shadows, dramatic rim lighting, film grain; ideal for fitness, martial arts, athletic discipline.
2. **Minimalist** (`minimalist`): Abundant negative space, clean lines, subtle accents, elegant typography; ideal for luxury, modern software, design.
3. **Corporate** (`corporate`): Neutral palette, crisp modern aesthetic, authoritative framing; ideal for B2B, SaaS, fintech, consulting.
4. **Commercial & Retail** (`commercial` / `product_ad`): Bright natural lighting, studio-isolated products, consumer-focused composition.
5. **Educational & Infographic** (`educational`): Structured visual hierarchy, pastel tones, clear typographic callouts, instructional layout.
6. **Inspirational** (`inspirational`): Warm golden-hour tones, soft bokeh, uplifting composition, emotional resonance.
7. **Editorial & Storytelling** (`editorial`): Magazine-style layouts, thoughtful typography, photojournalistic framing.

### Visual Settings Attributes
- `primary_color`, `secondary_color`, `accent_color`: Hex color codes.
- `primary_font`, `secondary_font`: Typography styling.
- `logo_placement`: `top_left`, `top_right`, `bottom_left`, `bottom_right`, `top_center`, `bottom_center`.
- `preferred_platforms`: Array of target social platforms.
- `preferred_aspect_ratios`: Standardized ratios (`1:1`, `9:16`, `16:9`, `4:5`, `1.91:1`).
- `image_generation_style_prompt`: Positive prompt style guidance.
- `image_generation_negative_prompt`: Negative prompt filters (e.g., avoid clutter, low resolution).

---

## 3. Multi-Provider AI Image Generation

The platform supports a pluggable provider abstraction for image generation.

| Provider | Model | Supported Sizes | Status |
|----------|-------|-----------------|--------|
| **OpenAI** | `dall-e-3` | 1024x1024, 1024x1792, 1792x1024 | Supported via `OPENAI_API_KEY` |
| **Stability AI** | `stable-diffusion-xl-1024-v1-0` | 1024x1024, 1152x896, 896x1152 | Supported via `STABILITY_API_KEY` |
| **Google Gemini** | `imagen-3.0-generate-002` | 1024x1024, 1024x1792, 1792x1024 | Supported via `GEMINI_API_KEY` |

> Note: When external provider credentials are not configured, the system explicitly returns `configuration_required` rather than fabricating synthetic or fake media.

### Image Generation Workflow
1. User selects a brand and optional Phase 6 content draft.
2. User configures generation parameters (objective, visual style, platform, aspect ratio, instructions).
3. Laravel API validates the request, verifies brand ownership, persists a `media_assets` record with status `queued`, creates a `media_generation_jobs` record, and returns HTTP 202 Accepted.
4. `ProcessMediaGenerationJob` dispatches to the queue.
5. Python AI Engine builds the prompt using Brand Visual Intelligence.
6. Configured provider generates the image asset.
7. Laravel downloads/stores the media asset into configured storage disk and updates DB status to `completed`.
8. User previews, edits, or exports from Web Media Studio or Flutter Mobile App.

---

## 4. AI Video Generation & Storyboard Engine

### Video Providers
- **Runway Gen-2 / Gen-3** (`runway`)
- **Stability Video Diffusion** (`stability_video`)
- **Pika Labs** (`pika`)

### Storyboard Engine (Independent Operation)
When external video generation APIs are unconfigured or when planning multi-scene video content, the **Video Storyboard Engine** functions independently to create scene-by-scene scripts:
- **Scene Number** & duration in seconds.
- **Visual Suggestion**: Camera angle, shot type, lighting.
- **Narration Text**: Voiceover script.
- **On-Screen Text**: Kinetic typography and captions.
- **Transition Style**: `fade_in`, `cut`, `dissolve`, `fast_cut`, `fade_out`.
- **Music & Sound Design Suggestion**.

---

## 5. Platform Media Specifications

| Platform | Format Name | Aspect Ratio | Dimensions | Typical Format |
|----------|-------------|--------------|------------|----------------|
| **Instagram** | `instagram_feed` | 1:1 | 1080 x 1080 | JPG / WebP |
| **Instagram** | `instagram_story` | 9:16 | 1080 x 1920 | JPG / MP4 |
| **Instagram** | `instagram_reel` | 9:16 | 1080 x 1920 | MP4 |
| **Facebook** | `facebook_feed` | 1.91:1 | 1200 x 628 | JPG |
| **TikTok** | `tiktok_video` | 9:16 | 1080 x 1920 | MP4 |
| **YouTube** | `youtube_thumbnail`| 16:9 | 1280 x 720 | JPG |
| **LinkedIn** | `linkedin_post` | 1.91:1 | 1200 x 627 | JPG |

---

## 6. REST API Reference

All routes require `auth:sanctum` and enforce strict brand ownership.

### Image & Video Generation
- `POST /api/v1/brands/{brand}/media/images/generate` — Dispatch image generation job.
- `GET /api/v1/media/images/{asset}/status` — Poll generation status for an asset.
- `POST /api/v1/brands/{brand}/media/videos/generate` — Dispatch video generation job.
- `GET /api/v1/media/videos/{asset}/status` — Poll video generation status.
- `POST /api/v1/media/jobs/{job}/retry` — Retry a failed generation job.

### Storyboards
- `POST /api/v1/brands/{brand}/media/storyboards/generate` — Create structured storyboard.
- `GET /api/v1/brands/{brand}/media/storyboards` — List brand storyboards.
- `GET /api/v1/media/storyboards/{storyboard}` — View single storyboard with scenes.
- `PUT /api/v1/media/storyboards/{storyboard}` — Update storyboard metadata.
- `DELETE /api/v1/media/storyboards/{storyboard}` — Delete storyboard.
- `PUT /api/v1/media/storyboard-scenes/{scene}` — Edit specific scene script.

### Media Library
- `GET /api/v1/brands/{brand}/media` — List paginated media assets (filters: `asset_type`, `platform`, `search`).
- `POST /api/v1/brands/{brand}/media/upload` — Upload user media asset.
- `GET /api/v1/media/{asset}` — View single media asset.
- `PUT /api/v1/media/{asset}` — Update asset metadata.
- `DELETE /api/v1/media/{asset}` — Soft-delete media asset.

### Brand Visual Settings
- `GET /api/v1/brands/{brand}/visual-settings` — Retrieve visual identity and styling.
- `PUT /api/v1/brands/{brand}/visual-settings` — Update visual identity and preferences.

---

## 7. Database Tables

1. `media_assets`: Core media records storing title, storage path, public URL, dimensions, aspect ratio, platform, provider, prompt, status, and metadata.
2. `media_generation_jobs`: Tracks asynchronous generation lifecycle, progress, error logs, and retry attempts.
3. `media_storyboards`: Video storyboard containers linked to brand and content draft.
4. `media_storyboard_scenes`: Individual storyboard scenes with visual cues, voiceover, and transitions.
5. `media_visual_settings`: Per-brand visual identity, color palette, typography, and prompt directives.
