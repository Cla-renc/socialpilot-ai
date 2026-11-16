<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Visual Studio · AI Generation</p>
                <h1>AI Image Generator</h1>
                <p class="muted">Generate professional on-brand visuals, posters, quote cards, and social graphics powered by AI.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('media.library') }}" class="button-secondary">Media Library</a>
                <a href="{{ route('media.dashboard') }}" class="button-secondary">Media Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('media.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('media.generator') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Image Generator</a>
        <a href="{{ route('media.video-studio') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Video Studio</a>
        <a href="{{ route('media.library') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Media Library</a>
    </div>

    @if ($brands->isEmpty())
        <div class="card panel" style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:2.5rem;margin-bottom:0.75rem;">🏢</div>
            <h3>No active brands available</h3>
            <p class="muted" style="margin-bottom:1.25rem;">You need an active brand before generating AI media assets.</p>
            <a href="{{ route('brands.create') }}" class="button">Create a Brand</a>
        </div>
    @else
        <div class="content-grid" id="generator-container" style="align-items:start;">
            {{-- Left column: Generation Form --}}
            <article class="card panel" id="generator-form-panel">
                <h2>Generation Settings</h2>

                <form id="image-generate-form" style="display:flex;flex-direction:column;gap:1.25rem;">
                    @csrf

                    {{-- Brand selector --}}
                    <div>
                        <label for="brand_id" class="label" style="display:block;margin-bottom:0.4rem;">
                            Brand <span style="color:var(--danger);">*</span>
                        </label>
                        <select id="brand_id" name="brand_id" required style="width:100%;">
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected($brand->id == $selected_brand_id)>
                                    {{ $brand->name }}
                                </option>
                            @endforeach
                        </select>
                        <div style="display:flex;justify-content:flex-end;margin-top:0.35rem;">
                            <a id="brand-visual-link" href="{{ route('brands.visual-settings', $selected_brand_id ?: $brands->first()->id) }}" class="muted" style="font-size:0.8rem;text-decoration:underline;">
                                Configure brand visual identity & colors →
                            </a>
                        </div>
                    </div>

                    {{-- Content Draft Selector --}}
                    <div>
                        <label for="content_draft_id" class="label" style="display:block;margin-bottom:0.4rem;">
                            Link to Content Draft (Optional)
                        </label>
                        <select id="content_draft_id" name="content_draft_id" style="width:100%;">
                            <option value="">None (Standalone creative graphic)</option>
                        </select>
                        <span class="muted" style="font-size:0.8rem;display:block;margin-top:0.3rem;">
                            Selecting a draft enriches the visual prompt with the draft's topic and hook.
                        </span>
                    </div>

                    {{-- Objective and Style --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <label for="objective" class="label" style="display:block;margin-bottom:0.4rem;">
                                Objective <span style="color:var(--danger);">*</span>
                            </label>
                            <select id="objective" name="objective" required style="width:100%;">
                                @foreach ($objectives as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="visual_style" class="label" style="display:block;margin-bottom:0.4rem;">
                                Visual Style <span style="color:var(--danger);">*</span>
                            </label>
                            <select id="visual_style" name="visual_style" required style="width:100%;">
                                @foreach ($visual_styles as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Platform --}}
                    <div>
                        <label for="platform" class="label" style="display:block;margin-bottom:0.4rem;">
                            Target Platform <span style="color:var(--danger);">*</span>
                        </label>
                        <select id="platform" name="platform" required style="width:100%;">
                            @foreach ($platforms as $p)
                                <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Aspect Ratio Selector with visual icons --}}
                    <div>
                        <span class="label" style="display:block;margin-bottom:0.5rem;">
                            Aspect Ratio <span style="color:var(--danger);">*</span>
                        </span>
                        <div id="aspect-ratio-selector" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(90px, 1fr));gap:0.75rem;">
                            @php
                                $ratioData = [
                                    '1:1' => ['label' => '1:1 Square', 'w' => 30, 'h' => 30, 'desc' => 'Instagram feed'],
                                    '4:5' => ['label' => '4:5 Portrait', 'w' => 26, 'h' => 34, 'desc' => 'Insta portrait'],
                                    '9:16' => ['label' => '9:16 Story', 'w' => 19, 'h' => 34, 'desc' => 'Reels / Stories'],
                                    '16:9' => ['label' => '16:9 Wide', 'w' => 36, 'h' => 20, 'desc' => 'YouTube / Web'],
                                    '1.91:1' => ['label' => '1.91:1 Link', 'w' => 38, 'h' => 20, 'desc' => 'Meta / LinkedIn'],
                                ];
                            @endphp

                            @foreach ($aspect_ratios as $ratio)
                                @php($info = $ratioData[$ratio] ?? ['label' => $ratio, 'w' => 30, 'h' => 30, 'desc' => ''])
                                <label class="ratio-card {{ $ratio === '1:1' ? 'ratio-card--active' : '' }}" style="display:flex;flex-direction:column;align-items:center;padding:0.75rem 0.5rem;border:1px solid var(--stroke);border-radius:10px;cursor:pointer;background:var(--surface);transition:all 0.2s ease;">
                                    <input type="radio" name="aspect_ratio" value="{{ $ratio }}" @checked($ratio === '1:1') style="display:none;">
                                    <div style="height:40px;display:flex;align-items:center;justify-content:center;margin-bottom:0.4rem;">
                                        <div class="ratio-box" style="width:{{ $info['w'] }}px;height:{{ $info['h'] }}px;border:2px solid currentColor;border-radius:3px;"></div>
                                    </div>
                                    <span style="font-size:0.82rem;font-weight:600;">{{ $ratio }}</span>
                                    <span class="muted" style="font-size:0.68rem;text-align:center;">{{ $info['desc'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Instructions textarea --}}
                    <div>
                        <label for="instructions" class="label" style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                            <span>Custom Prompt / Instructions (Optional)</span>
                            <span id="char-counter" class="muted" style="font-size:0.78rem;">0 / 500</span>
                        </label>
                        <textarea id="instructions" name="instructions" rows="3" maxlength="500" placeholder="e.g. Modern isometric style, minimalist product pedestal, pastel color scheme, subtle gradient glow..." style="width:100%;resize:vertical;"></textarea>
                    </div>

                    {{-- Submit button --}}
                    <div style="display:flex;gap:1rem;align-items:center;margin-top:0.5rem;">
                        <button type="submit" class="button" id="generate-submit-btn" style="flex:1;">
                            ✨ Generate Image
                        </button>
                    </div>
                </form>
            </article>

            {{-- Right column: Progress & Result Section --}}
            <aside id="generator-output-panel" style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Idle Placeholder state --}}
                <article class="card panel" id="idle-preview-card" style="text-align:center;padding:3rem 1.5rem;">
                    <div style="font-size:3rem;margin-bottom:1rem;opacity:0.8;">🎨</div>
                    <h3 style="margin:0 0 0.5rem;">Ready to generate</h3>
                    <p class="muted" style="font-size:0.9rem;max-width:340px;margin:0 auto;">
                        Choose your settings on the left and click "Generate Image" to create brand-aligned visuals.
                    </p>
                </article>

                {{-- Generation Progress Section (hidden until generating) --}}
                <article class="card panel" id="progress-section" style="display:none;text-align:center;padding:2.5rem 1.5rem;">
                    <div class="spinner" style="width:48px;height:48px;border:4px solid rgba(139,92,246,0.2);border-top:4px solid var(--primary);border-radius:50%;margin:0 auto 1.25rem;animation:spin 1s linear infinite;"></div>
                    <h3 id="progress-heading" style="margin:0 0 0.5rem;">Generating visual asset...</h3>
                    <p class="muted" id="progress-message" style="font-size:0.9rem;margin:0 0 1rem;">
                        Synthesizing brand visual settings and generating image with AI...
                    </p>
                    <div style="width:100%;background:rgba(255,255,255,0.06);height:6px;border-radius:999px;overflow:hidden;max-width:300px;margin:0 auto;">
                        <div id="progress-bar-fill" style="width:25%;height:100%;background:linear-gradient(90deg, var(--primary), var(--primary-2));border-radius:999px;transition:width 0.4s ease;"></div>
                    </div>
                    <span class="muted" id="progress-elapsed" style="display:block;margin-top:0.75rem;font-size:0.75rem;">Elapsed: 0s</span>
                </article>

                {{-- Result Preview Section (hidden until done) --}}
                <article class="card panel" id="result-section" style="display:none;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                        <h2 style="margin:0;font-size:1.15rem;" id="result-asset-title">Generated Image</h2>
                        <span class="badge badge--success" id="result-status-badge">Completed</span>
                    </div>

                    {{-- Image display container --}}
                    <div id="image-display-wrapper" style="position:relative;background:#030712;border-radius:12px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:260px;margin-bottom:1rem;border:1px solid var(--stroke);">
                        <img id="generated-image-el" src="" alt="Generated Visual" style="width:100%;max-height:480px;object-fit:contain;display:block;">
                    </div>

                    {{-- Metadata info --}}
                    <div class="list" style="margin-bottom:1.25rem;">
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Dimensions / Ratio</span>
                            <strong id="result-aspect-ratio">—</strong>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Provider / Engine</span>
                            <span id="result-provider" class="muted">—</span>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;" id="result-prompt-row">
                            <span class="muted">Prompt Summary</span>
                            <span id="result-prompt" style="max-width:65%;text-align:right;font-size:0.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                        <a id="download-asset-btn" href="#" target="_blank" download="socialpilot-asset.png" class="button" style="flex:1;text-align:center;">
                            ⬇ Download
                        </a>
                        <button type="button" id="save-feedback-btn" class="button-secondary" onclick="markSaved()">
                            ✓ Saved in Library
                        </button>
                    </div>

                    <div style="margin-top:1rem;display:flex;justify-content:space-between;align-items:center;">
                        <a href="{{ route('media.library') }}" class="muted" style="font-size:0.85rem;text-decoration:underline;">
                            Browse full library →
                        </a>
                        <button type="button" class="button-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;" onclick="generateAnother()">
                            Generate another
                        </button>
                    </div>
                </article>
            </aside>
        </div>
    @endif

    <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .ratio-card--active {
            border-color: var(--primary) !important;
            background: rgba(139, 92, 246, 0.15) !important;
            color: var(--primary-2) !important;
        }
        .ratio-card:hover {
            border-color: var(--primary);
        }
        textarea {
            font-family: inherit;
            padding: 0.65rem;
            border: 1px solid var(--stroke);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            color: var(--text);
        }
        select, input[type="text"] {
            padding: 0.65rem;
            border: 1px solid var(--stroke);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            color: var(--text);
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const brandSelect = document.getElementById('brand_id');
        const draftSelect = document.getElementById('content_draft_id');
        const brandVisualLink = document.getElementById('brand-visual-link');
        const form = document.getElementById('image-generate-form');
        const charCounter = document.getElementById('char-counter');
        const instructionsText = document.getElementById('instructions');

        if (!form) return;

        // Character counter
        instructionsText?.addEventListener('input', function() {
            charCounter.textContent = `${this.value.length} / 500`;
        });

        // Ratio card selection highlight
        const ratioLabels = document.querySelectorAll('#aspect-ratio-selector .ratio-card');
        ratioLabels.forEach(label => {
            label.addEventListener('click', function() {
                ratioLabels.forEach(l => l.classList.remove('ratio-card--active'));
                this.classList.add('ratio-card--active');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });

        // Brand change handler: update link and fetch drafts
        brandSelect?.addEventListener('change', function() {
            const brandId = this.value;
            if (brandVisualLink) {
                brandVisualLink.href = `/brands/${brandId}/visual-settings`;
            }
            loadBrandDrafts(brandId);
        });

        // Initial draft load
        if (brandSelect && brandSelect.value) {
            loadBrandDrafts(brandSelect.value);
        }

        async function loadBrandDrafts(brandId) {
            if (!draftSelect) return;
            draftSelect.innerHTML = '<option value="">Loading content drafts...</option>';

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/content?per_page=20`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });

                if (res.ok) {
                    const data = await res.json();
                    const drafts = data.data || data.drafts || (Array.isArray(data) ? data : []);

                    draftSelect.innerHTML = '<option value="">None (Standalone creative graphic)</option>';
                    drafts.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = `${d.title || 'Untitled Draft'} [${(d.platform || 'general').toUpperCase()}]`;
                        draftSelect.appendChild(opt);
                    });
                } else {
                    draftSelect.innerHTML = '<option value="">None (Standalone creative graphic)</option>';
                }
            } catch (err) {
                console.warn('Could not load drafts:', err);
                draftSelect.innerHTML = '<option value="">None (Standalone creative graphic)</option>';
            }
        }

        // Form Submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const brandId = brandSelect.value;
            const draftId = draftSelect.value || null;
            const objective = document.getElementById('objective').value;
            const visualStyle = document.getElementById('visual_style').value;
            const platform = document.getElementById('platform').value;
            const aspectRatio = document.querySelector('input[name="aspect_ratio"]:checked')?.value || '1:1';
            const instructions = instructionsText.value.trim();

            const payload = {
                content_draft_id: draftId,
                objective: objective,
                visual_style: visualStyle,
                platform: platform,
                aspect_ratio: aspectRatio,
                instructions: instructions
            };

            showGenerating(true);

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/media/images/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (!res.ok) {
                    const errorMsg = data.message || data.error || 'Image generation failed. Please try again.';
                    alert('Error: ' + errorMsg);
                    showGenerating(false);
                    return;
                }

                // If immediate asset returned
                const asset = data.asset || data.data || (data.id ? data : null);
                if (asset && (asset.generation_status === 'completed' || asset.public_url)) {
                    displayResult(asset);
                    showGenerating(false);
                    return;
                }

                // Poll job or asset status
                const assetId = asset?.id || data.asset_id || data.job_id;
                if (assetId) {
                    await pollAssetStatus(assetId);
                } else {
                    // Fallback to displaying whatever was returned
                    displayResult(asset || data);
                    showGenerating(false);
                }
            } catch (err) {
                console.error(err);
                alert('An unexpected error occurred during generation.');
                showGenerating(false);
            }
        });

        let pollTimer = null;
        let pollCount = 0;
        let elapsedSeconds = 0;
        let elapsedTimer = null;

        function showGenerating(isGenerating) {
            const submitBtn = document.getElementById('generate-submit-btn');
            const idleCard = document.getElementById('idle-preview-card');
            const progressSection = document.getElementById('progress-section');
            const resultSection = document.getElementById('result-section');
            const progressBar = document.getElementById('progress-bar-fill');
            const progressElapsed = document.getElementById('progress-elapsed');

            if (isGenerating) {
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Generating...';
                if (idleCard) idleCard.style.display = 'none';
                if (resultSection) resultSection.style.display = 'none';
                if (progressSection) progressSection.style.display = 'block';

                elapsedSeconds = 0;
                pollCount = 0;
                if (progressBar) progressBar.style.width = '20%';

                clearInterval(elapsedTimer);
                elapsedTimer = setInterval(() => {
                    elapsedSeconds++;
                    if (progressElapsed) progressElapsed.textContent = `Elapsed: ${elapsedSeconds}s`;
                    if (progressBar) {
                        const pct = Math.min(20 + elapsedSeconds * 3.5, 95);
                        progressBar.style.width = `${pct}%`;
                    }
                }, 1000);
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = '✨ Generate Image';
                if (progressSection) progressSection.style.display = 'none';
                clearInterval(elapsedTimer);
                if (pollTimer) clearTimeout(pollTimer);
            }
        }

        async function pollAssetStatus(assetId) {
            pollCount++;
            if (pollCount > 60) {
                showGenerating(false);
                alert('Generation request timed out after 3 minutes. The asset may still finish processing in the library.');
                window.location.href = "{{ route('media.library') }}";
                return;
            }

            try {
                const res = await fetch(`/api/v1/media/images/${assetId}/status`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });

                if (res.ok) {
                    const data = await res.json();
                    const asset = data.asset || data.data || data;

                    if (asset.generation_status === 'completed' || asset.status === 'completed' || asset.public_url) {
                        displayResult(asset);
                        showGenerating(false);
                        return;
                    }

                    if (asset.generation_status === 'failed' || asset.status === 'failed') {
                        showGenerating(false);
                        alert('Image generation failed: ' + (asset.generation_error || asset.error_message || 'Provider error.'));
                        return;
                    }
                }
            } catch (e) {
                console.warn('Status poll retry...', e);
            }

            // Retry after 3 seconds
            pollTimer = setTimeout(() => pollAssetStatus(assetId), 3000);
        }

        function displayResult(asset) {
            const resultSection = document.getElementById('result-section');
            const idleCard = document.getElementById('idle-preview-card');
            const imgEl = document.getElementById('generated-image-el');
            const titleEl = document.getElementById('result-asset-title');
            const ratioEl = document.getElementById('result-aspect-ratio');
            const providerEl = document.getElementById('result-provider');
            const promptEl = document.getElementById('result-prompt');
            const downloadBtn = document.getElementById('download-asset-btn');

            if (idleCard) idleCard.style.display = 'none';

            titleEl.textContent = asset.title || 'Generated Visual Asset';
            ratioEl.textContent = (asset.aspect_ratio || '1:1') + (asset.width ? ` (${asset.width}x${asset.height}px)` : '');
            providerEl.textContent = (asset.provider || 'AI Engine') + (asset.provider_model ? ` · ${asset.provider_model}` : '');
            promptEl.textContent = asset.generation_prompt || asset.title || 'Custom style';

            const url = asset.public_url || asset.url || '/assets/img/placeholder.jpg';
            imgEl.src = url;
            downloadBtn.href = url;

            if (resultSection) {
                resultSection.style.display = 'block';
                resultSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        window.markSaved = function() {
            const btn = document.getElementById('save-feedback-btn');
            btn.textContent = '✓ Saved in Library!';
            setTimeout(() => { btn.textContent = '✓ Saved in Library'; }, 2500);
        };

        window.generateAnother = function() {
            document.getElementById('result-section').style.display = 'none';
            document.getElementById('idle-preview-card').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
    });
    </script>
</x-app-layout>
