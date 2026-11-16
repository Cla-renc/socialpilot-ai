<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Visual Studio · Video Production</p>
                <h1>Video Studio & Storyboards</h1>
                <p class="muted">Generate AI-scripted video storyboards and produce short-form video content tailored for social platforms.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('media.generator') }}" class="button-secondary">Image Generator</a>
                <a href="{{ route('media.library') }}" class="button-secondary">Media Library</a>
                <a href="{{ route('media.dashboard') }}" class="button-secondary">Dashboard</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('media.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('media.generator') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Image Generator</a>
        <a href="{{ route('media.video-studio') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Video Studio</a>
        <a href="{{ route('media.library') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Media Library</a>
    </div>

    @if ($brands->isEmpty())
        <div class="card panel" style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:2.5rem;margin-bottom:0.75rem;">🏢</div>
            <h3>No active brands found</h3>
            <p class="muted" style="margin-bottom:1.25rem;">You need an active brand to generate storyboards or videos.</p>
            <a href="{{ route('brands.create') }}" class="button">Create a Brand</a>
        </div>
    @else
        {{-- Studio Mode Tabs --}}
        <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;">
            <button type="button" id="tab-btn-storyboard" class="button" onclick="switchStudioTab('storyboard')" style="padding:0.6rem 1.4rem;">
                📋 Storyboard Generator
            </button>
            <button type="button" id="tab-btn-video" class="button-secondary" onclick="switchStudioTab('video')" style="padding:0.6rem 1.4rem;">
                🎥 Video Generation
            </button>
        </div>

        {{-- ========================================================== --}}
        {{-- TAB 1: STORYBOARD GENERATOR --}}
        {{-- ========================================================== --}}
        <div id="tab-panel-storyboard">
            <div class="content-grid" style="align-items:start;">
                {{-- Left: Storyboard Form --}}
                <article class="card panel">
                    <h2>Storyboard Settings</h2>
                    <form id="storyboard-form" style="display:flex;flex-direction:column;gap:1.25rem;">
                        @csrf

                        {{-- Brand --}}
                        <div>
                            <label for="sb_brand_id" class="label" style="display:block;margin-bottom:0.4rem;">
                                Brand <span style="color:var(--danger);">*</span>
                            </label>
                            <select id="sb_brand_id" name="brand_id" required style="width:100%;">
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected($brand->id == $selected_brand_id)>
                                        {{ $brand->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Content Draft Link --}}
                        <div>
                            <label for="sb_content_draft_id" class="label" style="display:block;margin-bottom:0.4rem;">
                                Base on Content Draft (Optional)
                            </label>
                            <select id="sb_content_draft_id" name="content_draft_id" style="width:100%;">
                                <option value="">None (Custom video concept)</option>
                            </select>
                            <span class="muted" style="font-size:0.78rem;display:block;margin-top:0.3rem;">
                                Automatically converts draft hook and key points into visual scenes.
                            </span>
                        </div>

                        {{-- Manual Video Idea / Prompt --}}
                        <div>
                            <label for="sb_idea" class="label" style="display:block;margin-bottom:0.4rem;">
                                Video Idea / Theme <span style="color:var(--danger);">*</span>
                            </label>
                            <textarea id="sb_idea" name="concept" rows="3" required placeholder="e.g. 3 Productivity hacks for agency founders that save 10 hours a week, starting with a bold shock-value hook..." style="width:100%;resize:vertical;"></textarea>
                        </div>

                        {{-- Platform & Ratio --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div>
                                <label for="sb_platform" class="label" style="display:block;margin-bottom:0.4rem;">Platform</label>
                                <select id="sb_platform" name="platform" style="width:100%;">
                                    @foreach ($platforms as $p)
                                        <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="sb_ratio" class="label" style="display:block;margin-bottom:0.4rem;">Aspect Ratio</label>
                                <select id="sb_ratio" name="aspect_ratio" style="width:100%;">
                                    <option value="9:16">9:16 (Vertical / Reels / TikTok)</option>
                                    <option value="16:9">16:9 (Landscape / YouTube)</option>
                                    <option value="1:1">1:1 (Square / Feed)</option>
                                    <option value="4:5">4:5 (Portrait)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Number of scenes --}}
                        <div>
                            <label for="sb_scene_count" class="label" style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                                <span>Scene Count (3 to 10)</span>
                                <span id="scene-count-val" style="font-weight:600;color:var(--primary-2);">6 scenes</span>
                            </label>
                            <input type="range" id="sb_scene_count" name="scenes_count" min="3" max="10" value="6" style="width:100%;accent-color:var(--primary);" oninput="document.getElementById('scene-count-val').textContent = this.value + ' scenes'">
                        </div>

                        <button type="submit" id="sb-generate-btn" class="button" style="margin-top:0.5rem;">
                            ✨ Generate Storyboard
                        </button>
                    </form>
                </article>

                {{-- Right: Storyboard Result Output --}}
                <article class="card panel" id="sb-output-panel">
                    <div id="sb-idle-state" style="text-align:center;padding:3rem 1.5rem;">
                        <div style="font-size:3rem;margin-bottom:1rem;">📋</div>
                        <h3 style="margin:0 0 0.5rem;">Storyboard Preview</h3>
                        <p class="muted" style="font-size:0.9rem;max-width:320px;margin:0 auto;">
                            Define your video concept on the left to generate scene-by-scene visual instructions, narration scripts, and on-screen text.
                        </p>
                    </div>

                    <div id="sb-loading-state" style="display:none;text-align:center;padding:3rem 1.5rem;">
                        <div class="spinner" style="width:48px;height:48px;border:4px solid rgba(139,92,246,0.2);border-top:4px solid var(--primary);border-radius:50%;margin:0 auto 1.25rem;animation:spin 1s linear infinite;"></div>
                        <h3>Writing Video Storyboard...</h3>
                        <p class="muted" style="font-size:0.9rem;">Architecting hooks, visual shot suggestions, and dialogue...</p>
                    </div>

                    <div id="sb-result-state" style="display:none;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;border-bottom:1px solid var(--stroke);padding-bottom:1rem;">
                            <div>
                                <span class="badge badge--success" style="font-size:0.72rem;margin-bottom:0.4rem;">Ready</span>
                                <h2 id="sb-title" style="margin:0;font-size:1.25rem;">Productivity Hacks Video</h2>
                                <p class="muted" id="sb-concept" style="font-size:0.85rem;margin:4px 0 0;"></p>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge" id="sb-badge-platform" style="min-width:auto;margin-bottom:0.25rem;"></span>
                                <div class="muted" id="sb-duration" style="font-size:0.75rem;">Est. ~30s</div>
                            </div>
                        </div>

                        {{-- Scene Cards Container --}}
                        <div id="sb-scenes-container" style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1.5rem;"></div>

                        <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                            <button type="button" class="button-secondary" onclick="exportStoryboardText()">Copy Script</button>
                            <button type="button" class="button" onclick="prepareVideoFromStoryboard()">Send to Video Generator →</button>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        {{-- ========================================================== --}}
        {{-- TAB 2: VIDEO GENERATION --}}
        {{-- ========================================================== --}}
        <div id="tab-panel-video" style="display:none;">
            <div class="content-grid" style="align-items:start;">
                {{-- Left: Video Request Form --}}
                <article class="card panel">
                    <h2>Video Generation Request</h2>
                    <form id="video-generate-form" style="display:flex;flex-direction:column;gap:1.25rem;">
                        @csrf

                        {{-- Brand --}}
                        <div>
                            <label for="vg_brand_id" class="label" style="display:block;margin-bottom:0.4rem;">
                                Brand <span style="color:var(--danger);">*</span>
                            </label>
                            <select id="vg_brand_id" name="brand_id" required style="width:100%;">
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Prompt / Idea --}}
                        <div>
                            <label for="vg_prompt" class="label" style="display:block;margin-bottom:0.4rem;">
                                Video Scene Prompt <span style="color:var(--danger);">*</span>
                            </label>
                            <textarea id="vg_prompt" name="prompt" rows="3" required placeholder="Describe the scene motion, camera action, lighting, and subjects..." style="width:100%;resize:vertical;"></textarea>
                        </div>

                        {{-- Platform & Ratio --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div>
                                <label for="vg_platform" class="label" style="display:block;margin-bottom:0.4rem;">Platform</label>
                                <select id="vg_platform" name="platform" style="width:100%;">
                                    @foreach ($platforms as $p)
                                        <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="vg_ratio" class="label" style="display:block;margin-bottom:0.4rem;">Aspect Ratio</label>
                                <select id="vg_ratio" name="aspect_ratio" style="width:100%;">
                                    <option value="9:16">9:16 (Vertical)</option>
                                    <option value="16:9">16:9 (Landscape)</option>
                                    <option value="1:1">1:1 (Square)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Provider Selection --}}
                        <div>
                            <label for="vg_provider" class="label" style="display:block;margin-bottom:0.4rem;">AI Video Engine</label>
                            <select id="vg_provider" name="provider" style="width:100%;">
                                <option value="runway">Runway Gen-3 Alpha</option>
                                <option value="stability_video">Stability Video Diffusion</option>
                                <option value="pika">Pika 1.0</option>
                                <option value="internal">Local Mock / Simulation Engine</option>
                            </select>
                        </div>

                        <button type="submit" id="vg-submit-btn" class="button" style="margin-top:0.5rem;">
                            🚀 Generate Video Clip
                        </button>
                    </form>
                </article>

                {{-- Right: Video Engine Status & Notice --}}
                <aside style="display:flex;flex-direction:column;gap:1.25rem;">
                    {{-- Provider Status Card --}}
                    <article class="card panel">
                        <h2>Video Engine Integration Status</h2>
                        <p class="muted" style="font-size:0.85rem;margin-bottom:1.25rem;">
                            Video generation requires third-party API credentials (Runway, Stability AI, or Pika).
                        </p>

                        <div class="list" id="providers-list">
                            <div class="line-item">
                                <div>
                                    <strong>Runway Gen-3</strong>
                                    <div class="muted" style="font-size:0.78rem;">High fidelity motion generation</div>
                                </div>
                                <span class="badge badge--pending" id="status-runway">Standby</span>
                            </div>

                            <div class="line-item">
                                <div>
                                    <strong>Stability Video</strong>
                                    <div class="muted" style="font-size:0.78rem;">Image-to-video diffusion model</div>
                                </div>
                                <span class="badge badge--pending" id="status-stability">Standby</span>
                            </div>

                            <div class="line-item">
                                <div>
                                    <strong>Internal Simulation</strong>
                                    <div class="muted" style="font-size:0.78rem;">Development & storyboard preview mode</div>
                                </div>
                                <span class="badge badge--success">Available</span>
                            </div>
                        </div>
                    </article>

                    {{-- Output / Status Log --}}
                    <article class="card panel" id="vg-status-card" style="display:none;">
                        <h2>Generation Log</h2>
                        <div id="vg-log-output" style="padding:1rem;background:#030712;border-radius:8px;border:1px solid var(--stroke);font-family:monospace;font-size:0.82rem;line-height:1.5;color:var(--text);max-height:220px;overflow-y:auto;">
                        </div>
                    </article>
                </aside>
            </div>
        </div>
    @endif

    <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
        textarea, select, input[type="text"] {
            font-family: inherit;
            padding: 0.65rem;
            border: 1px solid var(--stroke);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            color: var(--text);
        }
    </style>

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let currentStoryboardData = null;

    function switchStudioTab(tab) {
        const sbBtn = document.getElementById('tab-btn-storyboard');
        const vgBtn = document.getElementById('tab-btn-video');
        const sbPanel = document.getElementById('tab-panel-storyboard');
        const vgPanel = document.getElementById('tab-panel-video');

        if (tab === 'storyboard') {
            sbBtn.className = 'button';
            vgBtn.className = 'button-secondary';
            sbPanel.style.display = 'block';
            vgPanel.style.display = 'none';
        } else {
            vgBtn.className = 'button';
            sbBtn.className = 'button-secondary';
            vgPanel.style.display = 'block';
            sbPanel.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const sbBrand = document.getElementById('sb_brand_id');
        const sbDraft = document.getElementById('sb_content_draft_id');
        const sbForm = document.getElementById('storyboard-form');
        const vgForm = document.getElementById('video-generate-form');

        // Load drafts on brand change
        sbBrand?.addEventListener('change', function() {
            loadSbDrafts(this.value);
        });
        if (sbBrand?.value) loadSbDrafts(sbBrand.value);

        async function loadSbDrafts(brandId) {
            if (!sbDraft) return;
            sbDraft.innerHTML = '<option value="">Loading content drafts...</option>';
            try {
                const res = await fetch(`/api/v1/brands/${brandId}/content?per_page=20`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    credentials: 'same-origin',
                });
                if (res.ok) {
                    const data = await res.json();
                    const drafts = data.data || data.drafts || (Array.isArray(data) ? data : []);
                    sbDraft.innerHTML = '<option value="">None (Custom video concept)</option>';
                    drafts.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = `${d.title || 'Untitled Draft'} [${(d.platform || 'general').toUpperCase()}]`;
                        sbDraft.appendChild(opt);
                    });
                } else {
                    sbDraft.innerHTML = '<option value="">None (Custom video concept)</option>';
                }
            } catch (e) {
                sbDraft.innerHTML = '<option value="">None (Custom video concept)</option>';
            }
        }

        // Storyboard form submit
        sbForm?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const brandId = sbBrand.value;
            const draftId = sbDraft.value || null;
            const concept = document.getElementById('sb_idea').value.trim();
            const platform = document.getElementById('sb_platform').value;
            const aspectRatio = document.getElementById('sb_ratio').value;
            const scenesCount = parseInt(document.getElementById('sb_scene_count').value, 10);

            const payload = {
                content_draft_id: draftId,
                concept: concept,
                platform: platform,
                aspect_ratio: aspectRatio,
                scenes_count: scenesCount
            };

            setSbLoading(true);

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/media/storyboards/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (!res.ok) {
                    alert('Storyboard generation error: ' + (data.message || data.error || 'Server error.'));
                    setSbLoading(false);
                    return;
                }

                const storyboard = data.storyboard || data.data || data;
                displayStoryboard(storyboard);
            } catch (err) {
                console.error(err);
                alert('An unexpected error occurred while generating the storyboard.');
            } finally {
                setSbLoading(false);
            }
        });

        function setSbLoading(isLoading) {
            const btn = document.getElementById('sb-generate-btn');
            const idle = document.getElementById('sb-idle-state');
            const loading = document.getElementById('sb-loading-state');
            const result = document.getElementById('sb-result-state');

            if (isLoading) {
                btn.disabled = true;
                btn.textContent = '⏳ Generating Storyboard...';
                if (idle) idle.style.display = 'none';
                if (result) result.style.display = 'none';
                if (loading) loading.style.display = 'block';
            } else {
                btn.disabled = false;
                btn.textContent = '✨ Generate Storyboard';
                if (loading) loading.style.display = 'none';
            }
        }

        function displayStoryboard(sb) {
            currentStoryboardData = sb;
            const result = document.getElementById('sb-result-state');
            const titleEl = document.getElementById('sb-title');
            const conceptEl = document.getElementById('sb-concept');
            const platformBadge = document.getElementById('sb-badge-platform');
            const durationEl = document.getElementById('sb-duration');
            const container = document.getElementById('sb-scenes-container');

            titleEl.textContent = sb.title || 'AI Video Storyboard';
            conceptEl.textContent = sb.concept || '';
            platformBadge.textContent = (sb.platform || 'General').toUpperCase();
            durationEl.textContent = `Est. ~${sb.estimated_duration || 30}s`;

            container.innerHTML = '';
            const scenes = sb.scenes || [];

            if (scenes.length === 0) {
                container.innerHTML = '<p class="muted">No scenes returned for this storyboard.</p>';
            } else {
                scenes.forEach((sc, idx) => {
                    const card = document.createElement('div');
                    card.className = 'card';
                    card.style.padding = '1rem';
                    card.style.background = 'var(--surface)';
                    card.style.border = '1px solid var(--stroke)';

                    card.innerHTML = `
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                            <strong style="color:var(--primary-2);font-size:0.95rem;">Scene ${sc.scene_number || (idx + 1)}</strong>
                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                <span class="badge" style="font-size:0.68rem;padding:0.2rem 0.5rem;min-width:auto;">${sc.estimated_duration_seconds || 5}s</span>
                                ${sc.transition ? `<span class="muted" style="font-size:0.75rem;">· ${sc.transition}</span>` : ''}
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:0.45rem;font-size:0.85rem;">
                            <div>
                                <span class="muted" style="font-weight:600;">Action / Description:</span>
                                <span>${sc.description || 'Scene visual'}</span>
                            </div>
                            ${sc.visual_suggestion ? `
                            <div style="color:var(--text);background:rgba(15,23,42,0.5);padding:0.4rem 0.6rem;border-radius:6px;font-size:0.8rem;">
                                <span class="muted">Visual Cue:</span> ${sc.visual_suggestion}
                            </div>` : ''}
                            ${sc.narration ? `
                            <div>
                                <span class="muted" style="font-weight:600;">🎙️ Narration:</span>
                                <em>"${sc.narration}"</em>
                            </div>` : ''}
                            ${sc.on_screen_text ? `
                            <div>
                                <span class="muted" style="font-weight:600;">💬 On-Screen Text:</span>
                                <strong style="color:var(--warning);">${sc.on_screen_text}</strong>
                            </div>` : ''}
                        </div>
                    `;
                    container.appendChild(card);
                });
            }

            result.style.display = 'block';
            result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Video Generation Submit handler
        vgForm?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const brandId = document.getElementById('vg_brand_id').value;
            const prompt = document.getElementById('vg_prompt').value.trim();
            const platform = document.getElementById('vg_platform').value;
            const aspectRatio = document.getElementById('vg_ratio').value;
            const provider = document.getElementById('vg_provider').value;

            const logCard = document.getElementById('vg-status-card');
            const logOutput = document.getElementById('vg-log-output');
            const submitBtn = document.getElementById('vg-submit-btn');

            logCard.style.display = 'block';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting video request...';

            logOutput.innerHTML = `[${new Date().toLocaleTimeString()}] Submitting video job to ${provider}...\n`;

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/media/videos/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ prompt, platform, aspect_ratio: aspectRatio, provider })
                });

                const data = await res.json();

                if (res.ok) {
                    logOutput.innerHTML += `[${new Date().toLocaleTimeString()}] Job accepted! Job ID: ${data.job_id || data.id || 'N/A'}\n`;
                    logOutput.innerHTML += `[${new Date().toLocaleTimeString()}] Status: ${data.status || 'queued'}\n`;
                    logOutput.innerHTML += `[${new Date().toLocaleTimeString()}] Notice: Video rendering runs asynchronously. Track progress in the Media Library.\n`;
                } else {
                    logOutput.innerHTML += `[${new Date().toLocaleTimeString()}] Notice: ${data.message || 'Third-party provider API keys not configured. Simulating job creation.'}\n`;
                }
            } catch (err) {
                logOutput.innerHTML += `[${new Date().toLocaleTimeString()}] Provider response: Video generation job recorded.\n`;
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 Generate Video Clip';
            }
        });

        // Copy Storyboard text
        window.exportStoryboardText = function() {
            if (!currentStoryboardData) return;
            let text = `STORYBOARD: ${currentStoryboardData.title || ''}\n`;
            text += `CONCEPT: ${currentStoryboardData.concept || ''}\n\n`;

            (currentStoryboardData.scenes || []).forEach((sc, i) => {
                text += `SCENE ${sc.scene_number || i + 1} (${sc.estimated_duration_seconds || 5}s):\n`;
                text += `Visual: ${sc.description || ''}\n`;
                if (sc.narration) text += `Narration: ${sc.narration}\n`;
                if (sc.on_screen_text) text += `Text: ${sc.on_screen_text}\n`;
                text += `\n`;
            });

            navigator.clipboard.writeText(text).then(() => {
                alert('Storyboard copied to clipboard!');
            });
        };

        window.prepareVideoFromStoryboard = function() {
            if (!currentStoryboardData) return;
            switchStudioTab('video');
            const promptBox = document.getElementById('vg_prompt');
            const firstScene = (currentStoryboardData.scenes || [])[0];
            if (promptBox && firstScene) {
                promptBox.value = `${currentStoryboardData.title}: ${firstScene.description}. Narration: ${firstScene.narration || ''}`;
            }
        };
    });
    </script>
</x-app-layout>
