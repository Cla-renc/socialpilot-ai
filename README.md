# SocialPilot AI

SocialPilot AI is a Laravel SaaS foundation for an autonomous AI social media management platform. Phase 1 established the base project, auth flows and SaaS schema; Phase 2 adds actual brand management with tenant-aware CRUD, relationship handling and a dedicated brand workspace.

## Phase 1 delivered

- Laravel 12 project initialized and configured for PHP 8.2
- SQLite-ready local database configuration for development
- Laravel Breeze authentication with login, registration, password reset and verification flows
- Core SaaS schema for users, plans, subscriptions, brands, brand settings, social accounts and token storage
- Dashboard shell with real counts from backend models
- Custom dark-mode web styling for a premium SaaS look
- Seeded subscription plans: Free, Starter, Professional and Enterprise

## Phase 2 delivered

- Brand CRUD functionality with user-scoped access control
- Brand details and settings pages for audience, tone, publishing behaviour, language and content formats
- Brand dashboard navigation and landing page links to brand management
- Brand service layer to keep business rules separate from controllers
- Brand data validation for creation and updates
- Tenant-safe ownership checks before editing or deleting records
- Feature tests covering brand creation and listing

## Phase 3 delivered

- Laravel API foundation under `/api/v1`
- Sanctum-powered authentication for mobile and SPA clients
- Auth endpoints for login, me, and logout
- Brand API endpoints with authenticated CRUD behaviour
- Subscription metadata endpoint exposing plans and active subscription status
- Initial mobile app structure (`mobile/`) aligned to the API contract
- Feature tests covering API login and authenticated brand creation/listing

## Phase 4 delivered

- AI publishing workflow foundation for per-brand content generation
- Saved content-draft records keyed to a user and brand
- Draft generation endpoint that turns a brand brief and prompt into structured content
- Social account OAuth adapters for Facebook/Instagram, TikTok, and YouTube with encrypted token storage
- Owner-scoped account discovery, status, refresh/check, disconnect, and mobile callback handling
- API tests covering content generation and social account connection flows

## Phase 5 delivered

- Publishing orchestration layer with queued and scheduled campaign posts
- `BrandPost` model and API foundation for per-brand content release management
- Queue-backed processing job that marks approved posts as published through a real orchestration flow
- Provider configuration metadata for internal, OpenAI, and Gemini AI backends
- API tests for queued publishing and brand post listing

## Phase 6 delivered

- Real AI content generation service with provider-aware output flow
- Internal fallback generation and external OpenAI/Gemini-ready response handling
- Brand analytics snapshot model, migration, and API endpoints
- Verified `/api/v1/brands/{brand}/generate-content` and `/api/v1/brands/{brand}/analytics` routes
- Automated tests covering AI generation and analytics tracking

## Phase 7 delivered

- Campaign planning and scheduling foundation for multi-post brand initiatives
- Real campaign model scoped to brand ownership and active date windows
- Campaign list/create/show API endpoints for the SaaS publishing workflow
- Campaign metadata and status tracking to support smarter publishing calendars
- Validation against unauthorized access to ensure brand safety and tenant isolation
- Automated API tests for campaign creation and ownership enforcement

## Phase 8 delivered

- Brand summary analytics endpoint for dashboard-style performance reporting
- Aggregated metrics covering follower counts, reach, impressions, engagement, posting activity, and campaign health
- Top-platform detection and recent publishing recaps for each brand workspace
- Summary API built for upcoming dashboard UI integration and executive reporting
- Automated validation for the summary endpoint and its brand-scoped security model

## Phase 9 delivered

- Collaboration foundation with role-aware brand member models, APIs, and access policy scaffolding
- Approval request workflow for review, approval, and publication decisions
- Collaboration dashboard view for members and pending approval activity
- Social provider OAuth adapters with one-time state validation and real account discovery
- External AI provider configuration scaffolding for OpenAI and Gemini with env-driven credentials
- Production deployment scaffolding via `docker-compose.yml` and `supervisord.conf`

## Run locally

1. Install dependencies:
   ```bash
   composer install
   ```
2. Copy environment file if needed:
   ```bash
   copy .env.example .env
   ```
3. Generate the application key:
   ```bash
   php artisan key:generate
   ```
4. Run database migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```
5. Start the application:
   ```bash
   php artisan serve
   ```
6. Visit http://localhost:8000

## Notes

- The project uses SQLite for local development. You can switch to MySQL in production by updating `.env` database settings.
- Asset compilation with Vite was not used in this phase because the environment blocked the Rollup native binary. The app uses a static custom stylesheet at `public/assets/socialpilot.css` to keep the dashboard fully functional without the bundler.
- Later-phase API, AI, publishing, analytics, campaign, and collaboration code is present, but its presence is not evidence that those integrations are complete or production-ready.
- The active local database may have later-phase migrations pending. Check `php artisan migrate:status` before using those modules; do not run migrations against a database containing important data without reviewing the pending migrations first.
- See [DEVELOPMENT_PROGRESS.md](./DEVELOPMENT_PROGRESS.md) for the audited phase status, known gaps, and verification performed.

## Phase checklist

- [x] Project structure and Laravel setup
- [x] MySQL/SQLite-ready configuration and SaaS foundations
- [x] Authentication foundation
- [x] Initial SaaS database migrations
- [x] Application shell and responsive dashboard
- [x] Validation with automated tests

## Test command

```bash
php artisan test
```
