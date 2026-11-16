<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ $brand->name }} · Brand Aesthetics</p>
                <h1>Brand Visual Settings</h1>
                <p class="muted">Define color palettes, fonts, logo placement, and AI visual style guidelines for {{ $brand->name }}.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('media.generator', ['brand_id' => $brand->id]) }}" class="button">+ Generate Image</a>
                <a href="{{ route('media.dashboard') }}" class="button-secondary">Media Studio</a>
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Brand Workspace</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1.5rem;border-left:4px solid var(--success);background:rgba(52, 211, 153, 0.08);">
            <strong>Success:</strong> {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card panel" role="alert" style="margin-bottom:1.5rem;border-left:4px solid var(--danger);background:rgba(248, 113, 113, 0.08);">
            <strong style="color:var(--danger);">Please review the errors below:</strong>
            <ul style="margin:0.5rem 0 0 1.25rem;padding:0;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('media.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('media.generator', ['brand_id' => $brand->id]) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Image Generator</a>
        <a href="{{ route('media.video-studio', ['brand_id' => $brand->id]) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Video Studio</a>
        <a href="{{ route('media.library', ['brand_id' => $brand->id]) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Media Library</a>
        <a href="{{ route('brands.visual-settings', $brand) }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Visual Settings</a>
    </div>

    <form method="POST" action="{{ route('brands.visual-settings.save', $brand) }}" style="display:flex;flex-direction:column;gap:1.5rem;">
        @csrf

        {{-- Section 1: Visual Identity Archetype --}}
        <section class="card panel">
            <h2>Visual Identity Archetype</h2>
            <p class="muted" style="font-size:0.85rem;margin-bottom:1.25rem;">
                Select the overarching visual aesthetic for {{ $brand->name }}. This steers the AI prompt composition.
            </p>

            @php
                $identities = [
                    'modern' => ['title' => 'Modern & Sleek', 'desc' => 'High contrast, clean lines, contemporary tech feel', 'colors' => ['#8b5cf6', '#22d3ee', '#0f172a']],
                    'minimalist' => ['title' => 'Minimalist & Zen', 'desc' => 'Generous negative space, calm tones, elegant simplicity', 'colors' => ['#f8fafc', '#94a3b8', '#0f172a']],
                    'vibrant' => ['title' => 'Vibrant & Bold', 'desc' => 'Punchy gradients, neon accents, high energy', 'colors' => ['#ec4899', '#f59e0b', '#3b82f6']],
                    'luxury' => ['title' => 'Luxury & Editorial', 'desc' => 'Gold/metallic accents, deep rich blacks, serif accents', 'colors' => ['#d97706', '#1c1917', '#fef3c7']],
                    'playful' => ['title' => 'Playful & Creative', 'desc' => 'Pastels, organic shapes, friendly approachable vibe', 'colors' => ['#06b6d4', '#10b981', '#f43f5e']],
                    'corporate' => ['title' => 'Corporate & Trusted', 'desc' => 'Navy blues, crisp slate, structured professional layout', 'colors' => ['#1e40af', '#64748b', '#f1f5f9']],
                ];
                $currentIdentity = old('visual_identity', $settings->visual_identity ?? 'modern');
            @endphp

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(230px, 1fr));gap:1rem;">
                @foreach ($identities as $key => $item)
                    <label class="identity-card {{ $currentIdentity === $key ? 'identity-card--active' : '' }}" style="display:flex;flex-direction:column;gap:0.5rem;padding:1rem;border:1px solid var(--stroke);border-radius:12px;background:var(--surface);cursor:pointer;transition:all 0.2s ease;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <strong style="font-size:0.95rem;">{{ $item['title'] }}</strong>
                            <input type="radio" name="visual_identity" value="{{ $key }}" @checked($currentIdentity === $key)>
                        </div>
                        <p class="muted" style="font-size:0.78rem;margin:0;line-height:1.4;">{{ $item['desc'] }}</p>
                        <div style="display:flex;gap:6px;margin-top:0.25rem;">
                            @foreach ($item['colors'] as $c)
                                <div style="width:24px;height:12px;border-radius:3px;background:{{ $c }};border:1px solid rgba(255,255,255,0.1);"></div>
                            @endforeach
                        </div>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Section 2: Brand Color Palette --}}
        <section class="card panel">
            <h2>Brand Color Palette</h2>
            <p class="muted" style="font-size:0.85rem;margin-bottom:1.25rem;">
                Exact hex codes injected into generated posters, backgrounds, and graphic overlays.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:1.25rem;">
                {{-- Primary --}}
                <div>
                    <label for="primary_color" class="label" style="display:block;margin-bottom:0.4rem;">Primary Brand Color</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color" id="primary_color" name="primary_color" value="{{ old('primary_color', $settings->primary_color ?? '#8b5cf6') }}" style="width:42px;height:40px;padding:2px;border:1px solid var(--stroke);border-radius:8px;background:transparent;cursor:pointer;">
                        <input type="text" id="primary_color_text" value="{{ old('primary_color', $settings->primary_color ?? '#8b5cf6') }}" style="flex:1;font-family:monospace;" oninput="document.getElementById('primary_color').value = this.value">
                    </div>
                </div>

                {{-- Secondary --}}
                <div>
                    <label for="secondary_color" class="label" style="display:block;margin-bottom:0.4rem;">Secondary Color</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color" id="secondary_color" name="secondary_color" value="{{ old('secondary_color', $settings->secondary_color ?? '#22d3ee') }}" style="width:42px;height:40px;padding:2px;border:1px solid var(--stroke);border-radius:8px;background:transparent;cursor:pointer;">
                        <input type="text" id="secondary_color_text" value="{{ old('secondary_color', $settings->secondary_color ?? '#22d3ee') }}" style="flex:1;font-family:monospace;" oninput="document.getElementById('secondary_color').value = this.value">
                    </div>
                </div>

                {{-- Accent --}}
                <div>
                    <label for="accent_color" class="label" style="display:block;margin-bottom:0.4rem;">Accent / Highlight</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color" id="accent_color" name="accent_color" value="{{ old('accent_color', $settings->accent_color ?? '#34d399') }}" style="width:42px;height:40px;padding:2px;border:1px solid var(--stroke);border-radius:8px;background:transparent;cursor:pointer;">
                        <input type="text" id="accent_color_text" value="{{ old('accent_color', $settings->accent_color ?? '#34d399') }}" style="flex:1;font-family:monospace;" oninput="document.getElementById('accent_color').value = this.value">
                    </div>
                </div>

                {{-- Background --}}
                <div>
                    <label for="background_color" class="label" style="display:block;margin-bottom:0.4rem;">Default Background</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color" id="background_color" name="background_color" value="{{ old('background_color', $settings->background_color ?? '#0f172a') }}" style="width:42px;height:40px;padding:2px;border:1px solid var(--stroke);border-radius:8px;background:transparent;cursor:pointer;">
                        <input type="text" id="background_color_text" value="{{ old('background_color', $settings->background_color ?? '#0f172a') }}" style="flex:1;font-family:monospace;" oninput="document.getElementById('background_color').value = this.value">
                    </div>
                </div>

                {{-- Text --}}
                <div>
                    <label for="text_color" class="label" style="display:block;margin-bottom:0.4rem;">Text / Foreground</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="color" id="text_color" name="text_color" value="{{ old('text_color', $settings->text_color ?? '#f8fafc') }}" style="width:42px;height:40px;padding:2px;border:1px solid var(--stroke);border-radius:8px;background:transparent;cursor:pointer;">
                        <input type="text" id="text_color_text" value="{{ old('text_color', $settings->text_color ?? '#f8fafc') }}" style="flex:1;font-family:monospace;" oninput="document.getElementById('text_color').value = this.value">
                    </div>
                </div>
            </div>
        </section>

        {{-- Section 3: Typography & Logo Placement --}}
        <section class="card panel">
            <h2>Typography & Logo Placement</h2>
            <p class="muted" style="font-size:0.85rem;margin-bottom:1.25rem;">
                Typography guidelines for graphic generation and watermark placement.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1.25rem;">
                {{-- Primary font --}}
                <div>
                    <label for="primary_font" class="label" style="display:block;margin-bottom:0.4rem;">Primary Header Font</label>
                    <select id="primary_font" name="primary_font" style="width:100%;">
                        @foreach (['Inter', 'Plus Jakarta Sans', 'Montserrat', 'Poppins', 'Space Grotesk', 'Playfair Display', 'Roboto', 'Outfit'] as $font)
                            <option value="{{ $font }}" @selected(old('primary_font', $settings->primary_font ?? 'Inter') === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Secondary font --}}
                <div>
                    <label for="secondary_font" class="label" style="display:block;margin-bottom:0.4rem;">Secondary / Body Font</label>
                    <select id="secondary_font" name="secondary_font" style="width:100%;">
                        @foreach (['Roboto', 'Inter', 'Open Sans', 'Lato', 'Source Sans Pro', 'Merriweather', 'Fira Code'] as $font)
                            <option value="{{ $font }}" @selected(old('secondary_font', $settings->secondary_font ?? 'Roboto') === $font)>{{ $font }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Base font size --}}
                <div>
                    <label for="font_size_base" class="label" style="display:block;margin-bottom:0.4rem;">Base Scale</label>
                    <select id="font_size_base" name="font_size_base" style="width:100%;">
                        <option value="14px" @selected(old('font_size_base', $settings->font_size_base) === '14px')>Compact (14px)</option>
                        <option value="16px" @selected(old('font_size_base', $settings->font_size_base ?? '16px') === '16px')>Standard (16px)</option>
                        <option value="18px" @selected(old('font_size_base', $settings->font_size_base) === '18px')>Large (18px)</option>
                    </select>
                </div>

                {{-- Logo placement --}}
                <div>
                    <label for="logo_placement" class="label" style="display:block;margin-bottom:0.4rem;">Logo Watermark Placement</label>
                    <select id="logo_placement" name="logo_placement" style="width:100%;">
                        <option value="bottom_right" @selected(old('logo_placement', $settings->logo_placement ?? 'bottom_right') === 'bottom_right')>Bottom Right</option>
                        <option value="bottom_left" @selected(old('logo_placement', $settings->logo_placement) === 'bottom_left')>Bottom Left</option>
                        <option value="top_right" @selected(old('logo_placement', $settings->logo_placement) === 'top_right')>Top Right</option>
                        <option value="top_left" @selected(old('logo_placement', $settings->logo_placement) === 'top_left')>Top Left</option>
                        <option value="center" @selected(old('logo_placement', $settings->logo_placement) === 'center')>Center</option>
                        <option value="none" @selected(old('logo_placement', $settings->logo_placement) === 'none')>Do not include logo</option>
                    </select>
                </div>
            </div>
        </section>

        {{-- Section 4: AI Generation Prompt Directives --}}
        <section class="card panel">
            <h2>AI Prompt Directives</h2>
            <p class="muted" style="font-size:0.85rem;margin-bottom:1.25rem;">
                These prompt instructions are automatically appended to all visual generation tasks for {{ $brand->name }}.
            </p>

            <div style="display:flex;flex-direction:column;gap:1.25rem;">
                {{-- Style Prompt --}}
                <div>
                    <label for="image_generation_style_prompt" class="label" style="display:block;margin-bottom:0.4rem;">
                        Brand Style Prompt (Positive Instructions)
                    </label>
                    <textarea id="image_generation_style_prompt" name="image_generation_style_prompt" rows="3" style="width:100%;resize:vertical;">{{ old('image_generation_style_prompt', $settings->image_generation_style_prompt ?? 'Professional, high quality, vibrant studio lighting, clean background') }}</textarea>
                    <span class="muted" style="font-size:0.78rem;">Example: "Editorial studio lighting, octane render, clean product composition, soft gradient backdrop, 8k resolution"</span>
                </div>

                {{-- Negative Prompt --}}
                <div>
                    <label for="image_generation_negative_prompt" class="label" style="display:block;margin-bottom:0.4rem;">
                        Negative Prompt (Attributes to Avoid)
                    </label>
                    <textarea id="image_generation_negative_prompt" name="image_generation_negative_prompt" rows="2" style="width:100%;resize:vertical;">{{ old('image_generation_negative_prompt', $settings->image_generation_negative_prompt ?? 'blurry, low quality, distorted, extra limbs, ugly, watermark') }}</textarea>
                    <span class="muted" style="font-size:0.78rem;">Example: "blurry, low quality, pixelated, distorted typography, cluttered background, cartoonish"</span>
                </div>

                {{-- Preferred Providers --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
                    <div>
                        <label for="preferred_image_provider" class="label" style="display:block;margin-bottom:0.4rem;">Preferred Image Provider</label>
                        <select id="preferred_image_provider" name="preferred_image_provider" style="width:100%;">
                            <option value="openai" @selected(old('preferred_image_provider', $settings->preferred_image_provider ?? 'openai') === 'openai')>OpenAI DALL-E 3</option>
                            <option value="stability" @selected(old('preferred_image_provider', $settings->preferred_image_provider) === 'stability')>Stability AI SDXL</option>
                            <option value="gemini" @selected(old('preferred_image_provider', $settings->preferred_image_provider) === 'gemini')>Google Imagen (Gemini)</option>
                            <option value="internal" @selected(old('preferred_image_provider', $settings->preferred_image_provider) === 'internal')>Internal / Local Engine</option>
                        </select>
                    </div>

                    <div>
                        <label for="preferred_video_provider" class="label" style="display:block;margin-bottom:0.4rem;">Preferred Video Provider</label>
                        <select id="preferred_video_provider" name="preferred_video_provider" style="width:100%;">
                            <option value="runway" @selected(old('preferred_video_provider', $settings->preferred_video_provider ?? 'runway') === 'runway')>Runway Gen-3</option>
                            <option value="stability_video" @selected(old('preferred_video_provider', $settings->preferred_video_provider) === 'stability_video')>Stability Video</option>
                            <option value="pika" @selected(old('preferred_video_provider', $settings->preferred_video_provider) === 'pika')>Pika 1.0</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        {{-- Save Action --}}
        <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:0.5rem;">
            <a href="{{ route('media.dashboard') }}" class="button-secondary">Cancel</a>
            <button type="submit" class="button" style="padding:0.8rem 2rem;">
                💾 Save Visual Settings
            </button>
        </div>
    </form>

    <style>
        .identity-card--active {
            border-color: var(--primary) !important;
            background: rgba(139, 92, 246, 0.12) !important;
        }
        .identity-card:hover {
            border-color: var(--primary);
        }
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
    document.addEventListener('DOMContentLoaded', function() {
        // Identity card switcher
        const cards = document.querySelectorAll('.identity-card');
        cards.forEach(card => {
            card.addEventListener('click', function() {
                cards.forEach(c => c.classList.remove('identity-card--active'));
                this.classList.add('identity-card--active');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });

        // Sync color pickers with text inputs
        ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color'].forEach(id => {
            const picker = document.getElementById(id);
            const text = document.getElementById(id + '_text');
            if (picker && text) {
                picker.addEventListener('input', () => { text.value = picker.value; });
            }
        });
    });
    </script>
</x-app-layout>
