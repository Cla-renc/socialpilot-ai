# Phase 6: AI Content Generation Agent Documentation

## Architecture & System Overview

SocialPilot AI's Phase 6 introduces an AI Content Generation Agent that produces original, brand-aligned, platform-adapted social media copy grounded in deterministic research from Phase 5 (Trend Intelligence).

```
                      ┌────────────────────────────────────────┐
                      │        Web (Blade/JS) / Flutter        │
                      └──────────────────┬─────────────────────┘
                                         │ Sanctum Auth / REST
                                         ▼
                      ┌────────────────────────────────────────┐
                      │          Laravel REST API & DB         │
                      │  - ContentGenerationApiController      │
                      │  - ContentLibraryApiController         │
                      │  - ContentSettingsApiController        │
                      │  - ContentGenerationService            │
                      │  - RunContentGeneration (Queue Job)    │
                      └──────────────────┬─────────────────────┘
                                         │ Bearer Token HTTP
                                         ▼
                      ┌────────────────────────────────────────┐
                      │     Python AI Engine (FastAPI)         │
                      │  - BrandContextService (Archetypes)    │
                      │  - ContentStrategyService (Angles)     │
                      │  - PlatformAdaptationService           │
                      │  - ComponentGenerators (Hook/CTA/etc)  │
                      │  - ContentVariationGenerator           │
                      │  - ContentImprovementService           │
                      │  - ContentQualityEvaluator (0-100)     │
                      │  - AIProviderManager (Ollama/External) │
                      └────────────────────────────────────────┘
```

---

## 1. Brand Context Intelligence & Archetypes

Rather than using generic prompts, the AI Content Generation Agent parses brand data from Laravel (`brands` and `brand_settings`) into structured archetypes:

| Archetype | Tone & Directives |
|-----------|-------------------|
| `discipline_coach` | Direct, demanding, no fluff, actionable protocols, focus on consistency and compounding habits. |
| `commercial_retail` | Benefit-driven, promotional clarity, clear value propositions, customer-centric hooks, concise CTAs. |
| `tech_authority` | Insightful, data-grounded, technical credibility, clear breakdown of complex ideas. |
| `wellness_guide` | Empathetic, balanced, mindful, supportive routines, holistic health focus. |
| `financial_strategist` | Analytical, structured, ROI-focused, risk awareness, strategic long-term perspectives. |
| `authentic_creator` | Conversational, relatable, vulnerable, narrative-driven, community-first. |

Missing brand fields fallback to sensible, clearly identified defaults without inventing business facts.

---

## 2. Trend-to-Content Integration

When users select a researched trend from Phase 5:
- The AI receives verifiable source evidence: trend title, summary, relevance score, momentum classification, and cited source URLs.
- Factual claims are strictly constrained to cited source evidence; the AI is instructed never to hallucinate statistics, news events, quotes, or URLs.
- Generated drafts maintain foreign-key linkage to their source trends via the `content_trend_sources` table.
- Users can also generate evergreen, educational, or promotional content without selecting a trend.

---

## 3. Platform-Specific Adaptation

Each platform has tailored structural adaptation rules:

| Platform | Format & Adaptations |
|----------|----------------------|
| **LinkedIn** | Professional tone, compelling 1-2 line opening hook, short line breaks, bulleted insights, professional CTA, 3-5 industry hashtags. |
| **Instagram** | Visual-first storytelling caption, emotive opening line, clean line spacing, strong CTA (save/share/comment), 5-15 relevant hashtags. |
| **TikTok** | High-energy opening hook (<3 seconds), conversational script with stage directions `[Hook]`, `[Body]`, `[CTA]`, sound suggestions, and trending hashtags. |
| **YouTube** | Catchy searchable title, engaging description with timestamp suggestions, video narration script with hook/body/CTA, keyword tags. |
| **Facebook** | Discussion-starting opening, community-friendly tone, clear body paragraphs, conversational question CTA, 2-3 hashtags. |

---

## 4. Quality Scoring & Evaluation

The `ContentQualityEvaluator` assesses generated content across 9 structural criteria:
1. `hook_strength`: Ability of the opening line to capture attention.
2. `platform_fit`: Alignment with platform format, length, and style.
3. `brand_alignment`: Consistency with archetype and brand voice.
4. `clarity`: Readability and conciseness.
5. `actionability`: Value and takeaway clarity for the audience.
6. `call_to_action_strength`: Clarity and urgency of the CTA.
7. `trend_integration`: Responsible use of cited trend evidence (when applicable).
8. `hashtag_relevance`: Accuracy and appropriateness of tags.
9. `structural_formatting`: Line breaks, emojis, and readability.

**Disclaimer:** Quality scores (0-100) are structural and contextual heuristics; they do **not** guarantee or predict algorithmic reach, virality, or audience engagement.

---

## 5. Variations & Improvement

- **Variations**: Generates 2-5 distinct stylistic versions (`professional`, `conversational`, `bold`, `educational`, `direct`).
- **Improvement Operations**: Applies targeted transformations:
  - `shorten`: Tightens prose and removes fluff.
  - `expand`: Elaborates with additional context, examples, or steps.
  - `improve_hook`: Rewrites the first line to be more compelling.
  - `better_cta`: Generates a stronger action request.
  - `add_storytelling`: Restructures into narrative story arc.
  - `professional` / `conversational`: Adjusts voice and tone.
  - `adapt_platform`: Reformats existing draft for another platform.
- **Revision History**: Every user edit or improvement operation records a snapshot in `content_revisions`.

---

## 6. Security, Ownership & Provenance

- **Ownership Boundary**: All API endpoints and web routes enforce brand ownership (`$brand->user_id === $request->user()->id`). Foreign access returns HTTP 403.
- **AI Provenance**: Drafts generated via external/local AI providers have `is_ai_generated = true`. Deterministic fallback drafts have `is_ai_generated = false`.
- **Credential Isolation**: AI provider credentials (OpenAI, Gemini, Ollama, OpenRouter) remain server-side. No API keys are sent to clients.

---

## 7. Test Verification Summary

| Suite | Pre-Phase 6 | Post-Phase 6 | Status |
|-------|-------------|--------------|--------|
| **Laravel Feature & Unit Tests** | 85 | **106** | **PASS** (648 assertions) |
| **Python AI Engine Tests** | 25 | **39** | **PASS** |
| **Flutter Mobile Tests** | 23 | **34** | **PASS** |
| **Flutter Static Analysis** | Clean | **Clean** (0 issues) | **PASS** |
