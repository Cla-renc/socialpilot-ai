# AI SCHEDULING AND AUTOPILOT AGENT DOCUMENTATION (PHASE 8)

## 1. Overview & Objective

The **AI Scheduling and Autopilot Agent (Phase 8)** transforms SocialPilot AI from a content generation and media creation platform into an intelligent social media planning and automated scheduling SaaS platform.

It operates strictly on four integrated tiers:
1. **Laravel Backend & REST API**: Central authority for user authentication, brand authorization, multi-platform scheduled post persistence, calendar queries, publishing queue management, and autopilot execution.
2. **Python/FastAPI AI Engine**: Strategic intelligence provider that analyzes brand archetypes, audience behaviors, and platform timing algorithms to supply Best Time to Post recommendations and schedule proposals.
3. **Web Application (Laravel Blade)**: Responsive Scheduling Studio providing interactive calendar visualization, draft-to-schedule wizards, queue prioritization, autopilot controls, and full activity logs.
4. **Flutter Mobile Application**: Complete on-the-go scheduling studio with calendar tracking, quick post scheduler, autopilot toggle controls, and AI best times analysis.

> [!IMPORTANT]
> **Strict Phase Boundary Notice**:
> Phase 8 schedules, stages, organizes, and verifies content for future publishing. **Actual publishing across social network APIs is reserved for Phase 9.** The system never manufactures fake publishing confirmations or claims content has been posted when it has only been scheduled.

---

## 2. Architecture & Request Flow

```
┌────────────────────────┐      ┌────────────────────────┐
│  Laravel Blade Web     │      │   Flutter Mobile App   │
│  (Scheduling Studio)   │      │   (Scheduling Hub)     │
└───────────┬────────────┘      └───────────┬────────────┘
            │                               │
            └───────────────┬───────────────┘
                            ▼
        ┌───────────────────────────────────────┐
        │   Laravel 11 REST API & Auth (Sanctum) │
        │   - ScheduledPostApiController        │
        │   - SchedulingCalendarApiController   │
        │   - PublishingQueueApiController      │
        │   - SchedulingRecommendationApiCtrl   │
        │   - AutopilotApiController            │
        │   - SchedulingSettingsApiController   │
        └───────────────────┬───────────────────┘
                            │
               ┌────────────┴────────────┐
               ▼                         ▼
   ┌───────────────────────┐ ┌─────────────────────────┐
   │ SQLite / MySQL DB     │ │ Python/FastAPI AI Engine│
   │ - scheduled_posts     │ │ - Best Time to Post     │
   │ - scheduled_post_plat │ │ - Scheduling Strategy   │
   │ - publishing_queues   │ │ - Conflict Detection    │
   │ - autopilot_settings  │ │ - Content Distribution  │
   │ - autopilot_rules     │ │ - Autopilot Strategy    │
   │ - autopilot_logs      │ │                         │
   └───────────────────────┘ └─────────────────────────┘
```

---

## 3. Database Schema

The Phase 8 database migration introduces nine (9) core scheduling tables with strict foreign key constraints:

1. **`scheduled_posts`**:
   - `id`, `brand_id`, `user_id`, `content_draft_id` (nullable reference to Phase 6 draft)
   - `title`, `caption`, `body`, `hashtags` (JSON)
   - `scheduled_at` (UTC timestamp)
   - `timezone` (Configured local timezone e.g. `America/New_York`)
   - `status` (`draft`, `scheduled`, `queued`, `pending_approval`, `published`, `cancelled`, `failed`)
   - `requires_approval` (boolean), `is_approved` (boolean), `approved_by`, `approved_at`
   - `autopilot_managed` (boolean), `autopilot_rule_id`
   - `cancellation_reason`

2. **`scheduled_post_platforms`**:
   - Enables independent per-platform customizations (captions, titles, hashtags, aspect ratios, independent scheduled times) across Facebook, Instagram, TikTok, YouTube, and LinkedIn.
   - `scheduled_post_id`, `social_account_id`, `platform`, `status`, `custom_title`, `custom_caption`, `custom_body`, `custom_hashtags`

3. **`scheduled_post_media`**:
   - Junction table linking Phase 7 `media_assets` to scheduled posts with ordering and platform validation.

4. **`publishing_queues`**:
   - Staging queues for brand content with configurable posting intervals (`posting_interval_minutes`), preferred days, active/paused status, and priority.

5. **`publishing_queue_items`**:
   - Individual queued posts with position ordering, queue priority, and execution status.

6. **`autopilot_settings`**:
   - Per-brand autopilot preferences:
     - `mode`: `manual` (Mode A), `ai_assisted` (Mode B), `automatic` (Mode C)
     - `is_enabled` (boolean), `posts_per_week`, `min_gap_hours`
     - `preferred_days` (JSON array of weekdays)
     - `preferred_hours` (JSON array of peak hours)
     - `blackout_periods` (JSON array of restricted windows)
     - `requires_approval` (Human-in-the-loop safety toggle)

7. **`autopilot_rules`**:
   - Custom business logic rules (`rule_type`, `conditions`, `actions`, `priority`, `is_active`) allowing automated category scheduling (e.g., educational posts on Tuesdays, product showcases on Thursdays).

8. **`autopilot_activity_logs`**:
   - Immutable audit trail recording every autonomous action, schedule proposal, rule trigger, and manual override.

9. **`scheduling_recommendations`**:
   - Cached recommendations linking suggested days, peak hours, confidence scores, evidence classification (`historical` vs `heuristic`), and rationales.

---

## 4. AI Engine Modules & Algorithms

The Python AI engine (`ai_engine/`) supplies 7 dedicated scheduling intelligence modules:

1. **`best_time_to_post.py`**:
   - Computes platform-tailored peak engagement windows for Facebook, Instagram, TikTok, YouTube, and LinkedIn.
   - Categorizes brand archetype combining niche, category, industry, and brand voice.
   - Differentiates proven historical patterns from heuristic estimates.
2. **`scheduling_intelligence.py`**:
   - Evaluates audience characteristics, category velocity, and weekly cadence to build comprehensive brand scheduling strategies.
3. **`scheduling_conflict_detection.py`**:
   - Scans proposed post times against existing scheduled posts, account overlaps, and configured blackout hours.
4. **`autopilot_strategy.py`**:
   - Plans multi-slot posting distributions across the weekly calendar, strictly enforcing minimum gap intervals and approval prerequisites.
5. **`content_distribution.py`**:
   - Balances content categories across available slots to prevent repetitive messaging.
6. **`publishing_schedule.py`**:
   - Generates weekly publishing proposals adapted per platform.
7. **`scheduling_recommendations.py`**:
   - Facade coordinator invoked by the FastAPI endpoints.

---

## 5. Autopilot Operating Modes

The Autopilot Agent supports three distinct modes of operation:

### Mode A: Manual Scheduling
- User exercises total control over every schedule decision.
- Autopilot runs conflict detection and provides advice, but never stages or schedules posts automatically.

### Mode B: AI-Assisted Scheduling (Recommended Default)
- AI engine analyzes drafts and recommends optimal times and platforms.
- Autopilot prepares schedule proposals and queues them for user review.
- Changes require explicit human approval before being committed to the calendar.

### Mode C: Autonomous Autopilot
- Autopilot scans ready content drafts from Phase 6 and approved media from Phase 7.
- Automatically calculates the next available conflict-free slot according to active rules.
- If `requires_approval` is active, posts remain in `pending_approval` status until verified; if disabled, posts transition to `scheduled`.
- All actions are logged to `autopilot_activity_logs`.

---

## 6. Background Queue & Scheduler Configuration

Laravel console scheduler commands and queued jobs handle background operations:

- **`socialpilot:autopilot-process`** (`ProcessAutopilotCommand`):
  - Scheduled to run hourly.
  - Dispatches `ProcessAutopilotSchedulingJob` for every active autopilot brand.
- **`socialpilot:queue-process`** (`ProcessPublishingQueueCommand`):
  - Scheduled to run every 15 minutes.
  - Dispatches `ScheduleQueuedContentJob` to assign next available calendar slots to queued items.
- **`SendUpcomingPostReminderJob`**:
  - Sends dashboard and database notifications to brand owners 24 hours and 1 hour before scheduled posts.
- **`RefreshSchedulingRecommendationsJob`**:
  - Periodically refreshes cached AI best-time recommendations.

---

## 7. REST API Reference

All endpoints are authenticated via Laravel Sanctum and scoped to the brand owner.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/brands/{brand}/scheduled-posts` | List scheduled posts with search & platform filters |
| `POST` | `/api/v1/brands/{brand}/scheduled-posts` | Create new scheduled post (single or multi-platform) |
| `GET` | `/api/v1/scheduled-posts/{id}` | Show detailed scheduled post record |
| `PUT` | `/api/v1/scheduled-posts/{id}` | Update post content, caption, or metadata |
| `DELETE` | `/api/v1/scheduled-posts/{id}` | Delete / discard scheduled post |
| `POST` | `/api/v1/scheduled-posts/{id}/reschedule` | Update scheduled date and time |
| `POST` | `/api/v1/scheduled-posts/{id}/cancel` | Cancel post with audit reason |
| `POST` | `/api/v1/scheduled-posts/{id}/approve` | Approve a pending scheduled post |
| `GET` | `/api/v1/brands/{brand}/calendar` | Fetch calendar entries for a date range |
| `GET` | `/api/v1/brands/{brand}/calendar/summary` | Monthly calendar summary statistics |
| `GET` | `/api/v1/brands/{brand}/publishing-queue` | Get or initialize brand publishing queue |
| `POST` | `/api/v1/brands/{brand}/publishing-queue` | Create / configure publishing queue |
| `POST` | `/api/v1/publishing-queue/{id}/pause` | Pause publishing queue |
| `POST` | `/api/v1/publishing-queue/{id}/resume` | Resume publishing queue |
| `POST` | `/api/v1/publishing-queue/{id}/reorder` | Reorder queue items by position |
| `GET` | `/api/v1/brands/{brand}/scheduling/best-times` | Get AI Best Time to Post recommendations |
| `POST` | `/api/v1/brands/{brand}/scheduling/conflicts` | Pre-flight conflict check for a proposed slot |
| `GET` | `/api/v1/brands/{brand}/autopilot` | Get autopilot configuration |
| `PUT` | `/api/v1/brands/{brand}/autopilot` | Update autopilot mode, targets, and days |
| `POST` | `/api/v1/brands/{brand}/autopilot/enable` | Enable autopilot |
| `POST` | `/api/v1/brands/{brand}/autopilot/pause` | Pause autopilot |
| `POST` | `/api/v1/brands/{brand}/autopilot/resume` | Resume autopilot |
| `POST` | `/api/v1/brands/{brand}/autopilot/disable` | Disable autopilot |
| `POST` | `/api/v1/brands/{brand}/autopilot/trigger` | Manually run autopilot cycle immediately |
| `GET` | `/api/v1/brands/{brand}/autopilot/activity` | Retrieve paginated audit activity log |
| `POST` | `/api/v1/brands/{brand}/autopilot/rules` | Create new autopilot rule |
| `PUT` | `/api/v1/brands/{brand}/autopilot/rules/{id}` | Update autopilot rule |
| `DELETE` | `/api/v1/brands/{brand}/autopilot/rules/{id}` | Delete autopilot rule |
| `GET` | `/api/v1/brands/{brand}/scheduling-settings` | View brand scheduling settings & timezone |
| `PUT` | `/api/v1/brands/{brand}/scheduling-settings` | Update timezone, frequency, and blackout periods |

---

## 8. Verification & Test Evidence

- **Laravel Feature Tests**: `153 passed (915 assertions)` including `Phase8SchedulingTest` and `SchedulingStudioWebTest`.
- **Python AI Engine Tests**: `63 passed` including `test_scheduling_ai.py` (12 tests).
- **Flutter Analyzer**: `0 issues found`.
- **Flutter Widget & Unit Tests**: Full test suite passing across all mobile components.
