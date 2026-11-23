# Stage 5.5.4.8 — T-06 Internal AI Content Generation Production Preflight Report

**Project:** SocialPilot AI  
**Repository:** `Cla-renc/socialpilot-ai`  
**Branch:** `main`  
**Deployed Commit:** `7cbd0bca529c112eaa9dd536414ee36ad691e5cc` (`7cbd0bc`)  
**Production Web Service Name:** `socialpilot-ai`  
**Production URL:** `https://socialpilot-ai-y4lm.onrender.com`  
**Database:** `socialpilot` (TiDB Cloud Serverless, `gateway01.eu-central-1.prod.aws.tidbcloud.com:4000`)  
**Runtime Database Account:** `AZDARrqgy4MBFGE.prod_app` (DML only, TLS active: `TLS_AES_128_GCM_SHA256`)  
**AI Engine Architecture:** Colocated Python FastAPI service (`ai_engine`) on loopback `http://127.0.0.1:8080`  
**Stage:** 5.5.4.8 (T-06 Internal AI Content Generation Production Preflight)  
**Audit Mode:** STRICT READ-ONLY (No content generated, zero database mutations, no LLM invocations)  
**Report Generated:** 2026-11-23T14:05:00+03:00  

---

## 1. FINAL DECISION

```
A — READY FOR SEPARATELY AUTHORIZED LOCAL-ONLY PRODUCTION GENERATION TESTING
```

> [!IMPORTANT]
> **PREFLIGHT AUDIT SUMMARY & DECISION RATIONALE:**
> 1. **Local-Only Internal Engine Verified:** Content generation in production is powered by two distinct, zero-cost mechanisms:
>    - **Laravel Deterministic Generator (`AiGenerationService`):** Generates structured drafts with brand voice and tone completely within PHP without network dependencies.
>    - **Colocated FastAPI Engine (`ai_engine` on `127.0.0.1:8080`):** Authenticated via `AI_ENGINE_INTERNAL_TOKEN`. When requested with `provider: "internal"`, it uses deterministic component generators (`HookGenerator`, `CaptionGenerator`, `HashtagGenerator`, `BrandContextService`) with **zero external API calls**.
> 2. **Provider Isolation & Zero External Key Exposure:** Neither OpenAI, Gemini, Groq, nor OpenRouter API keys are configured in the production Render environment. Ollama is **not** installed in the container, and port `11434` is inactive. The application cannot make external paid AI calls.
> 3. **Migration 29 Integrity Constraints Active:** Direct database inspection confirms both Migration 29 unique indexes are present:
>    - `bcd_generation_job_id_unique` on `brand_content_drafts.generation_job_id`.
>    - `cr_draft_id_revision_number_unique` on `content_revisions(draft_id, revision_number)`.
> 4. **Tenant Isolation & Zero Current Content:** Database tables `brand_content_drafts`, `content_revisions`, `content_generation_jobs`, `content_variations`, and `agent_executions` currently contain **`0` rows**. Existing User `30001` and Brand `30001` have zero drafts and cannot be modified.
> 5. **Clean Cascade Teardown Verified:** All foreign keys on `brands.id` enforce `ON DELETE CASCADE`. Removing the synthetic test brand will automatically and completely delete all generated drafts, revisions, variations, and job records.
> 6. **STRICT FINAL STOP MAINTAINED:** No content was generated, no drafts were persisted, and no endpoints were called.

---

## 2. Laravel Content Generation Architecture Audit

The Laravel application provides two active, Sanctum-guarded endpoints for content generation:

### 2.1 Direct Generation Endpoint (`POST /api/v1/brands/{brand}/generate-content`)
- **Controller:** `App\Http\Controllers\Api\V1\AiGenerationController::generate`
- **Underlying Service:** `App\Services\AiGenerationService`
- **Authorization:** `abort_unless($brand->user_id === $request->user()->id, 403)`
- **Request Validation:**
  - `prompt` (required, string, 10–2000 chars)
  - `provider` (optional, in: `internal`, `openai`, `gemini`, `groq`, `openrouter`, `ollama`; default: `internal`)
  - `platform` (optional, in: `linkedin`, `x`, `instagram`, `tiktok`, `facebook`; default: `linkedin`)
  - `content_type` (optional, in: `caption`, `carousel`, `thread`, `video`; default: `caption`)
  - `status` (optional, in: `draft`, `approved`, `scheduled`, `published`; default: `draft`)
- **Execution Profile:** Runs inline in PHP when `provider === 'internal'`. Creates a `BrandContentDraft`, logs an `AgentExecution`, and returns `HTTP 201 Created`.

### 2.2 Advanced Agent Generation Endpoint (`POST /api/v1/brands/{brand}/content/generate`)
- **Controller:** `App\Http\Controllers\Api\V1\ContentGenerationApiController::generate`
- **Underlying Service:** `App\Services\ContentGenerationService`
- **Authorization:** `abort_unless($brand->user_id === $request->user()->id, 403)`
- **Request Validation:**
  - `objective` (in: `awareness`, `engagement`, `education`, etc.)
  - `platform` (in: `linkedin`, `instagram`, `tiktok`, `youtube`, `facebook`)
  - `content_format` (in: `post`, `text_post`, `caption`, `short_script`, `article`, etc.)
  - `instructions` (optional, max 2000 chars)
  - `provider` (in: `internal`, `ollama`, `openai`, `gemini`, `groq`, `openrouter`; default: `internal`)
  - `sync` (optional boolean; `true` for inline HTTP response, `false` for background queue dispatch)
- **Execution Profile:** With `sync: true` and `provider: 'internal'`, it makes an internal loopback call to FastAPI on `http://127.0.0.1:8080`, creates a `ContentGenerationJob` (`completed`), creates the `BrandContentDraft`, creates `ContentRevision` (`revision_number = 1`), and returns `HTTP 201 Created`.

---

## 3. FastAPI Internal Engine Audit (`ai_engine`)

A code-level audit was conducted across the Python FastAPI microservice:

| Component | Code Location | Inspected Behavior | Evaluation |
| :--- | :--- | :--- | :---: |
| **Process Management** | `docker/supervisor/supervisord.conf` | `program:fastapi` runs `uvicorn ai_engine.main:app --host 127.0.0.1 --port 8080` | ✅ Bound to loopback |
| **Authentication Guard** | `ai_engine/main.py` (`_verify_internal_token`) | Timing-safe `hmac.compare_digest` against `AI_ENGINE_INTERNAL_TOKEN` | ✅ Timing-safe |
| **Health Probe** | `GET /health` | Returns `{"status": "ok", "service": "trend-and-content-engine"}` | ✅ Publicly shielded |
| **Generation Route** | `POST /internal/v1/content/generate` | Validates Bearer token, receives brand context, returns structured draft JSON | ✅ Internal only |
| **Provider Routing** | `ai_engine/content_service.py` | If `provider == 'internal'`, routes exclusively to deterministic generators | ✅ Zero external calls |
| **Component Generators**| `ai_engine/component_generators.py` | Generates hooks, captions, call-to-actions, hashtags from brand metadata | ✅ Pure local execution |

---

## 4. Zero-Cost, Ollama & Provider Isolation Audit

| Provider Candidate | Production Configuration Status | Runtime Reality | Zero-Cost Evaluation |
| :--- | :--- | :--- | :---: |
| **`internal`** | Configured as default (`services.ai.default_provider`) | Fully functional in container on `127.0.0.1:8080` & PHP | ✅ 100% FREE & LOCAL |
| **`ollama`** | `OLLAMA_BASE_URL` not configured | Binary **not** in container; port 11434 inactive | ⚠️ UNAVAILABLE |
| **`llama3.2:3b`** | Model weights **not** in container | Render Free tier (512MB RAM) cannot run 3B model | ⚠️ UNAVAILABLE |
| **`openai`** | `OPENAI_API_KEY` is empty/absent | Call fails or falls back to internal template | ✅ ZERO SPEND RISK |
| **`gemini`** | `GEMINI_API_KEY` is empty/absent | Call fails or falls back to internal template | ✅ ZERO SPEND RISK |
| **`groq`** | `GROQ_API_KEY` is empty/absent | Call fails or falls back to internal template | ✅ ZERO SPEND RISK |
| **`openrouter`** | `OPENROUTER_API_KEY` is empty/absent | Call fails or falls back to internal template | ✅ ZERO SPEND RISK |

### Core Finding:
Local-only execution is **guaranteed** when passing `provider: "internal"`. Because no third-party API keys exist in the environment and Ollama is not installed, the application cannot incur third-party costs under any circumstance.

---

## 5. Database State & Migration 29 Verification

Read-only database queries via `AZDARrqgy4MBFGE.prod_app` over TLS (`TLS_AES_128_GCM_SHA256`):

| Table Name | Live Row Count | Schema Notes | Cascade Rule on Brand Deletion |
| :--- | :---: | :--- | :---: |
| **`users`** | **`1`** | User ID `30001` intact | — |
| **`brands`** | **`1`** | Brand ID `30001` intact (`draft`) | — |
| **`brand_content_drafts`** | **`0`** | `bcd_generation_job_id_unique` active | `ON DELETE CASCADE` |
| **`content_revisions`** | **`0`** | `cr_draft_id_revision_number_unique` active | `ON DELETE CASCADE` |
| **`content_generation_jobs`** | **`0`** | Indexed on `brand_id`, `user_id` | `ON DELETE CASCADE` |
| **`content_variations`** | **`0`** | Child of `brand_content_drafts` | `ON DELETE CASCADE` |
| **`agent_executions`** | **`0`** | Audit log table | `ON DELETE SET NULL` |
| **`content_generation_settings`**| **`0`** | Child of `brands` | `ON DELETE CASCADE` |

### Migration 29 Integrity Check:
- Unique index `bcd_generation_job_id_unique` (`generation_job_id`): **PRESENT**
- Unique index `cr_draft_id_revision_number_unique` (`draft_id, revision_number`): **PRESENT**

---

## 6. Synthetic Test Design (T-06 Specification)

For the authorized execution stage, the synthetic test content generation will follow this exact contract:

| Attribute | Synthetic Value | Design Rationale |
| :--- | :--- | :--- |
| **Target Brand** | `[PROD-TEST] AI Generation Verification` | Isolated temporary synthetic brand (status: `draft`). |
| **Prompt / Topic** | `The importance of consistent brand communication.` | Harmless, non-copyrighted generic business insight. |
| **Platform** | `linkedin` | Standard platform formatting. |
| **Content Type / Format**| `caption` / `post` | Single standard social post. |
| **Provider** | `internal` | Guaranteed local deterministic generation ($0 cost). |
| **Execution Mode** | `sync: true` | Synchronous execution to avoid unmonitored queue processing. |

---

## 7. Proposed Controlled Test Sequence (T-06)

When authorized, the T-06 execution stage will execute the following controlled steps:

```
Step 1: Ephemeral Identity & Brand Provisioning
        ├── Register synthetic user `prod-ai-test-<uuid>@example.invalid`.
        ├── Create synthetic brand `[PROD-TEST] AI Generation Verification` (status: `draft`).
        └── Capture synthetic Bearer token and brand ID.

Step 2: Negative Validation Probe
        ├── POST /api/v1/brands/{brandId}/generate-content with empty body.
        └── Expect HTTP 422 Unprocessable Content. Zero drafts created.

Step 3: Controlled Internal Content Generation
        ├── POST /api/v1/brands/{brandId}/generate-content with provider: 'internal'.
        ├── Expect HTTP 201 Created.
        └── Capture generated Draft ID; verify title, body, and ai_provider: 'internal'.

Step 4: Draft Retrieval Verification
        ├── GET /api/v1/brands/{brandId}/content-drafts/{draftId}.
        └── Expect HTTP 200 OK. Confirm content matches generated draft.

Step 5: Cross-Tenant Isolation Probe
        ├── Attempt to access Brand 30001 with synthetic Bearer token.
        └── Expect HTTP 403 Forbidden.

Step 6: Targeted Cascade Teardown
        ├── DELETE /api/v1/brands/{brandId} via API.
        ├── Delete synthetic user and tokens via scoped SQL queries.
        └── Verify brand_content_drafts and content_generation_jobs return to 0.

Step 7: Baseline Restoration Verification
        └── Confirm users = 1, brands = 1 (User 30001 & Brand 30001 intact).
        └── Recheck health endpoints (/up, /api/v1/health, /api/v1/health/ready).
```

---

## 8. Audit Conclusion & Next Steps

The internal AI content generation subsystem is robust, zero-cost verified, and fully ready for controlled testing. By strictly passing `provider: "internal"`, the application executes purely on local resources with zero dependency on external LLMs, zero financial cost, and complete multi-tenant safety.

**Final Decision: A — Ready for separately authorized local-only production generation testing.**

---

## 9. STRICT FINAL STOP

**ALL ACTIONS HAVE STOPPED.**

No drafts were generated. No jobs were created. No users or brands were provisioned. No production records were modified.

Await explicit authorization before proceeding to the execution stage (**Stage 5.5.4.9: T-06 Internal AI Content Generation Execution**).
