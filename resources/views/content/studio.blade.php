<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ $brand->name }} · AI Content</p>
                <h1>Content Studio</h1>
                <p class="muted">Generate original social media content powered by your brand identity and trend research.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.content.index', $brand) }}" class="button-secondary">Content library</a>
                <a href="{{ route('brands.trends.index', $brand) }}" class="button-secondary">Trend intelligence</a>
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Brand workspace</a>
            </div>
        </div>
    </x-slot>

    <section class="content-grid" id="studio-grid">
        {{-- ── Generation Form ── --}}
        <article class="card panel" id="studio-form-panel">
            <h2>Generation settings</h2>

            {{-- Brand picker --}}
            @if ($brands->count() > 1)
            <div class="form-row" style="margin-bottom:1rem;">
                <label>
                    <span class="label">Brand</span>
                    <select id="brand-switcher" aria-label="Switch brand">
                        @foreach ($brands as $b)
                            <option value="{{ route('brands.content.studio', $b) }}" @selected($b->id === $brand->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @endif

            {{-- Inline Error Alert --}}
            <div id="studio-error" style="display:none;margin-bottom:1rem;padding:0.75rem 1rem;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:8px;color:#ef4444;font-size:0.9rem;" role="alert">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-weight:600;">⚠ Error:</span>
                        <span id="studio-error-msg"></span>
                    </div>
                    <button type="button" onclick="clearInlineError()" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:1.1rem;line-height:1;padding:0;" aria-label="Dismiss error">&times;</button>
                </div>
            </div>

            <form id="generate-form" style="display:flex;flex-direction:column;gap:1rem;">
                @csrf
                <input type="hidden" name="brand_id" value="{{ $brand->id }}">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <label>
                        <span class="label">Platform <span style="color:var(--color-danger)">*</span></span>
                        <select name="platform" id="platform" required>
                            <option value="">Select platform</option>
                            <option value="linkedin">LinkedIn</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="youtube">YouTube</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </label>

                    <label>
                        <span class="label">Objective <span style="color:var(--color-danger)">*</span></span>
                        <select name="objective" id="objective" required>
                            <option value="">Select objective</option>
                            <option value="awareness">Brand awareness</option>
                            <option value="engagement">Engagement</option>
                            <option value="education">Education</option>
                            <option value="community">Community building</option>
                            <option value="brand_authority">Brand authority</option>
                            <option value="promotion">Product promotion</option>
                            <option value="traffic">Website traffic</option>
                            <option value="lead_generation">Lead generation</option>
                        </select>
                    </label>

                    <label>
                        <span class="label">Category</span>
                        <select name="category" id="category">
                            <option value="">Any category</option>
                            <option value="educational">Educational</option>
                            <option value="motivational">Motivational</option>
                            <option value="promotional">Promotional</option>
                            <option value="storytelling">Storytelling</option>
                            <option value="thought_leadership">Thought leadership</option>
                            <option value="community">Community</option>
                            <option value="announcement">Announcement</option>
                            <option value="question">Question / discussion</option>
                        </select>
                    </label>

                    <label>
                        <span class="label">Content format</span>
                        <select name="content_format" id="content_format">
                            <option value="">Auto-select</option>
                            <option value="text_post">Text post</option>
                            <option value="caption">Caption</option>
                            <option value="short_script">Short-form script</option>
                            <option value="long_form">Long-form post</option>
                            <option value="educational_post">Educational post</option>
                            <option value="storytelling_post">Storytelling post</option>
                            <option value="linkedin_article">LinkedIn article</option>
                            <option value="thread">Thread</option>
                        </select>
                    </label>
                </div>

                {{-- Trend selector --}}
                <label>
                    <span class="label">Base content on a researched trend (optional)</span>
                    <select name="trend_id" id="trend-selector">
                        <option value="">No trend — generate original content</option>
                        @foreach ($trends as $trend)
                            <option value="{{ $trend->id }}">
                                [{{ $trend->relevance_score }}/100{{ $trend->momentum ? ' · ' . ucfirst($trend->momentum) : '' }}]
                                {{ Str::limit($trend->title, 90) }}
                            </option>
                        @endforeach
                    </select>
                </label>
                @if ($trends->isEmpty())
                    <p class="muted" style="margin-top:-0.5rem;font-size:0.85rem;">
                        No trend research found for this brand.
                        <a href="{{ route('brands.trends.index', $brand) }}">Run trend research</a> to unlock trend-based generation.
                    </p>
                @endif

                <label>
                    <span class="label">Additional instructions (optional)</span>
                    <textarea name="instructions" id="instructions" rows="3"
                        placeholder="e.g. Focus on the morning routine habit stack, include a personal story angle, keep it under 300 words…"
                        style="width:100%;resize:vertical;"></textarea>
                </label>

                <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                    <button type="submit" class="button" id="generate-btn">Generate content</button>
                    <span class="muted" id="gen-status" style="display:none;font-size:0.9rem;">Generating…</span>
                </div>
            </form>
        </article>

        {{-- ── Result Panel ── --}}
        <article class="card panel" id="result-panel" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                <h2 style="margin:0;" id="result-title">Generated content</h2>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <button class="button-secondary" id="copy-btn" onclick="copyContent()">Copy</button>
                    <button class="button-secondary" id="regenerate-btn" onclick="regenerate()">Regenerate</button>
                    <button class="button" id="save-btn" onclick="saveDraft()">Save to library</button>
                </div>
            </div>

            {{-- Quality badge --}}
            <div id="quality-row" style="margin-bottom:1rem;display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <span class="badge" id="quality-badge">Quality: —</span>
                <span class="muted" id="platform-badge"></span>
            </div>

            {{-- Content sections --}}
            <div class="list" id="content-sections">
                <div class="line-item" id="hook-row" style="display:none;">
                    <span class="muted">Hook</span>
                    <strong id="result-hook"></strong>
                </div>
                <div style="margin:1rem 0;" id="body-row">
                    <p class="label" style="margin-bottom:0.25rem;">Body</p>
                    <div id="result-body" style="white-space:pre-wrap;line-height:1.6;"></div>
                </div>
                <div class="line-item" id="caption-row" style="display:none;">
                    <span class="muted">Caption</span>
                    <span id="result-caption" style="max-width:70%;text-align:right;"></span>
                </div>
                <div class="line-item" id="cta-row" style="display:none;">
                    <span class="muted">Call to action</span>
                    <strong id="result-cta"></strong>
                </div>
                <div class="line-item" id="hashtags-row" style="display:none;">
                    <span class="muted">Hashtags</span>
                    <span id="result-hashtags" style="max-width:70%;text-align:right;word-break:break-word;"></span>
                </div>
            </div>

            {{-- Quality evaluation --}}
            <div id="quality-eval" style="margin-top:1rem;display:none;">
                <h3>Quality evaluation</h3>
                <p class="muted" style="font-size:0.85rem;">Scores reflect structural and contextual criteria. They do not predict virality or audience response.</p>
                <div id="quality-criteria" style="display:flex;flex-direction:column;gap:0.35rem;margin-top:0.5rem;"></div>
                <div id="quality-issues" style="margin-top:0.75rem;"></div>
            </div>

            {{-- Hidden fields for save --}}
            <input type="hidden" id="saved-draft-id" value="">

            <div style="margin-top:1rem;text-align:right;">
                <a href="#" id="view-draft-link" style="display:none;" class="button-secondary">View full editor →</a>
            </div>
        </article>
    </section>

    <style>
        #studio-grid { align-items: start; }
        textarea { font-family: inherit; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; }
        select, input[type="text"] { width: 100%; }
        .quality-criterion { display: flex; justify-content: space-between; font-size: 0.85rem; }
        .quality-criterion span:last-child { font-weight: 600; }
        .quality-issue { font-size: 0.85rem; padding: 0.3rem 0; color: #c44; }
        .quality-suggestion { font-size: 0.85rem; padding: 0.3rem 0; color: #666; }
    </style>

    <script>
    const BRAND_ID = {{ $brand->id }};
    let currentDraftId = null;
    let lastPayload = null;
    let isGenerating = false;

    function showInlineError(message) {
        const errorBox = document.getElementById('studio-error');
        const errorMsg = document.getElementById('studio-error-msg');
        if (errorBox && errorMsg) {
            errorMsg.textContent = message;
            errorBox.style.display = 'block';
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function clearInlineError() {
        const errorBox = document.getElementById('studio-error');
        const errorMsg = document.getElementById('studio-error-msg');
        if (errorBox && errorMsg) {
            errorBox.style.display = 'none';
            errorMsg.textContent = '';
        }
    }

    document.getElementById('brand-switcher')?.addEventListener('change', function() {
        window.location.href = this.value;
    });

    document.getElementById('generate-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        await runGeneration();
    });

    async function runGeneration(overrides = {}) {
        if (isGenerating) return;

        clearInlineError();

        const form = document.getElementById('generate-form');
        const data = Object.fromEntries(new FormData(form));
        Object.assign(data, overrides, { sync: true });

        // Clean empty optional fields so they are treated as null/omitted
        if (data.trend_id === '') data.trend_id = null;
        if (data.category === '') data.category = null;
        if (data.content_format === '') data.content_format = null;

        // Validation
        if (!data.platform) {
            showInlineError('Please select a platform before generating content.');
            return;
        }
        if (!data.objective) {
            showInlineError('Please select a content objective before generating content.');
            return;
        }

        setGenerating(true);

        lastPayload = data;

        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.content : '';

            const res = await fetch(`/api/v1/brands/${BRAND_ID}/content/generate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify(data),
            });

            // Safely parse JSON if response is JSON
            let json = null;
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                try {
                    json = await res.json();
                } catch (_) {
                    json = null;
                }
            }

            if (!res.ok) {
                let errorMsg = 'Generation failed. Please try again.';

                if (json) {
                    if (json.errors && typeof json.errors === 'object') {
                        const errList = Object.values(json.errors).flat().filter(Boolean);
                        if (errList.length > 0) {
                            errorMsg = errList.join(' ');
                        } else if (json.message) {
                            errorMsg = json.message;
                        }
                    } else if (json.message) {
                        errorMsg = json.message;
                    } else if (json.error) {
                        errorMsg = json.error;
                    }
                } else {
                    if (res.status === 401 || res.status === 403) {
                        errorMsg = 'Your session may have expired or you are unauthorized. Please refresh the page and log in again.';
                    } else if (res.status === 419) {
                        errorMsg = 'Page token expired. Please refresh the page and try again.';
                    } else if (res.status >= 500) {
                        errorMsg = `Server error (HTTP ${res.status}). Our AI engine was unable to complete the request. Please try again.`;
                    } else {
                        errorMsg = `Request failed with HTTP status ${res.status}. Please try again.`;
                    }
                }

                showInlineError(errorMsg);
                return;
            }

            if (json && json.draft) {
                clearInlineError();
                displayResult(json.draft);
            } else {
                showInlineError('Content generation completed, but no draft was returned by the server.');
            }
        } catch (err) {
            console.error('Content generation network or parsing error:', err);
            showInlineError('A network error or unexpected server failure occurred. Please check your connection and try again.');
        } finally {
            setGenerating(false);
        }
    }

    function displayResult(draft) {
        currentDraftId = draft.id;

        document.getElementById('result-title').textContent = draft.title || 'Generated content';

        const platform = (document.getElementById('platform').value || 'content').toUpperCase();
        document.getElementById('platform-badge').textContent = platform;

        // Quality
        const qs = draft.quality_score;
        const badge = document.getElementById('quality-badge');
        if (qs !== null && qs !== undefined) {
            badge.textContent = `Quality: ${qs}/100`;
            badge.className = 'badge ' + (qs >= 75 ? 'badge--success' : qs >= 50 ? 'badge--pending' : 'badge--danger');
        } else {
            badge.textContent = 'Quality: N/A';
            badge.className = 'badge';
        }

        // Hook
        if (draft.hook) {
            document.getElementById('result-hook').textContent = draft.hook;
            document.getElementById('hook-row').style.display = '';
        } else {
            document.getElementById('hook-row').style.display = 'none';
        }

        // Body
        document.getElementById('result-body').textContent = draft.body || '';

        // Caption
        if (draft.caption) {
            document.getElementById('result-caption').textContent = draft.caption;
            document.getElementById('caption-row').style.display = '';
        } else {
            document.getElementById('caption-row').style.display = 'none';
        }

        // CTA
        if (draft.call_to_action) {
            document.getElementById('result-cta').textContent = draft.call_to_action;
            document.getElementById('cta-row').style.display = '';
        } else {
            document.getElementById('cta-row').style.display = 'none';
        }

        // Hashtags
        const tags = draft.hashtags;
        if (tags && (Array.isArray(tags) ? tags.length : typeof tags === 'string' && tags.trim())) {
            const tagText = Array.isArray(tags) ? tags.join(' ') : tags;
            document.getElementById('result-hashtags').textContent = tagText;
            document.getElementById('hashtags-row').style.display = '';
        } else {
            document.getElementById('hashtags-row').style.display = 'none';
        }

        // Quality evaluation
        const evalData = draft.quality_evaluation;
        if (evalData && typeof evalData === 'object') {
            const criteriaEl = document.getElementById('quality-criteria');
            criteriaEl.innerHTML = '';
            if (evalData.criteria) {
                Object.entries(evalData.criteria).forEach(([key, score]) => {
                    const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    criteriaEl.insertAdjacentHTML('beforeend',
                        `<div class="quality-criterion"><span>${label}</span><span>${score}/10</span></div>`);
                });
            }
            const issuesEl = document.getElementById('quality-issues');
            issuesEl.innerHTML = '';
            (evalData.issues || []).forEach(issue => {
                issuesEl.insertAdjacentHTML('beforeend', `<div class="quality-issue">⚠ ${issue}</div>`);
            });
            (evalData.suggestions || []).forEach(sug => {
                issuesEl.insertAdjacentHTML('beforeend', `<div class="quality-suggestion">💡 ${sug}</div>`);
            });
            document.getElementById('quality-eval').style.display = '';
        } else {
            document.getElementById('quality-eval').style.display = 'none';
        }

        // View link
        const viewLink = document.getElementById('view-draft-link');
        viewLink.href = `/brands/${BRAND_ID}/content/${draft.id}`;
        viewLink.style.display = 'inline-block';

        document.getElementById('result-panel').style.display = '';
        document.getElementById('result-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function setGenerating(state) {
        isGenerating = Boolean(state);
        const btn = document.getElementById('generate-btn');
        const status = document.getElementById('gen-status');
        if (btn) {
            btn.disabled = isGenerating;
            btn.textContent = isGenerating ? 'Generating…' : 'Generate content';
        }
        if (status) {
            status.style.display = isGenerating ? '' : 'none';
        }
    }

    async function regenerate() {
        if (!lastPayload) return;
        await runGeneration(lastPayload);
    }

    async function saveDraft() {
        if (!currentDraftId) return;
        // Draft is already persisted — just navigate to it
        window.location.href = `/brands/${BRAND_ID}/content/${currentDraftId}`;
    }

    function copyContent() {
        const hook = document.getElementById('result-hook').textContent;
        const body = document.getElementById('result-body').textContent;
        const cta  = document.getElementById('result-cta').textContent;
        const tags = document.getElementById('result-hashtags').textContent;
        const parts = [hook, body, cta, tags].filter(Boolean);
        navigator.clipboard.writeText(parts.join('\n\n')).then(() => {
            const btn = document.getElementById('copy-btn');
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
        });
    }
    </script>
</x-app-layout>
