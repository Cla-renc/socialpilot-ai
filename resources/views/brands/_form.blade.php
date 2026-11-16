@php
    $current = $brand ?? null;
    $value = fn ($key, $fallback = null) => old($key, $fallback);
    $arrayValue = fn ($key, $fallback = []) => old($key, $fallback);
    $inputStyle = 'width:100%;margin-top:8px;padding:.8rem .9rem;border-radius:12px;border:1px solid rgba(148,163,184,.18);background:rgba(15,23,42,.6);color:#e2e8f0;';
@endphp

<section class="card panel" style="max-width:1000px;margin:0 auto;">
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif
        <div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(220px,1fr));">
            <h2 style="grid-column:1/-1;">Brand identity</h2>
            <div>
                <label class="muted" for="name">Brand name</label>
                <input id="name" name="name" value="{{ $value('name', $current?->name) }}" required style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="status">Status</label>
                <select id="status" name="status" style="{{ $inputStyle }}">
                    @foreach (['draft' => 'Draft', 'active' => 'Active', 'paused' => 'Paused'] as $status => $label)
                        <option value="{{ $status }}" @selected($value('status', $current?->status ?? 'draft') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="muted" for="industry">Industry</label>
                <input id="industry" name="industry" value="{{ $value('industry', $current?->industry ?? $current?->category) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="niche">Niche</label>
                <input id="niche" name="niche" value="{{ $value('niche', $current?->niche) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="website_url">Website URL</label>
                <input id="website_url" name="website_url" type="url" value="{{ $value('website_url', $current?->website_url) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="logo">Logo (PNG, JPEG, or WebP; up to 5 MB)</label>
                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" style="{{ $inputStyle }}">
                @if ($current?->logo_url)
                    <img src="{{ $current->logo_url }}" alt="{{ $current->name }} logo" style="max-width:96px;max-height:96px;margin-top:10px;border-radius:12px;">
                @endif
            </div>
            <div style="grid-column:1/-1;">
                <label class="muted" for="description">Description</label>
                <textarea id="description" name="description" rows="3" style="{{ $inputStyle }}">{{ $value('description', $current?->description) }}</textarea>
            </div>
            <h2 style="grid-column:1/-1;">Audience profile</h2>
            <div>
                <label class="muted" for="audience">Target audience</label>
                <input id="audience" name="audience" value="{{ $value('audience', $current?->audience) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="target_age_group">Target age group</label>
                <input id="target_age_group" name="target_age_group" value="{{ $value('target_age_group', $current?->target_age_group) }}" placeholder="e.g. 25-34" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="audience_interests">Audience interests (comma-separated)</label>
                <input id="audience_interests" name="audience_interests" value="{{ $value('audience_interests', implode(', ', $current?->audience_interests ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="region">Region</label>
                <input id="region" name="region" value="{{ $value('region', $current?->region) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="language">Language code</label>
                <input id="language" name="language" value="{{ $value('language', $current?->language ?? 'en') }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="tone">Brand tone</label>
                <input id="tone" name="tone" value="{{ $value('tone', $current?->tone) }}" style="{{ $inputStyle }}">
            </div>
            <h2 style="grid-column:1/-1;">Voice and content preferences</h2>
            <div>
                <label class="muted" for="brand_voice">Brand voice</label>
                <input id="brand_voice" name="brand_voice" value="{{ $value('brand_voice', $current?->settings?->brand_voice) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="writing_style">Writing style</label>
                <input id="writing_style" name="writing_style" value="{{ $value('writing_style', $current?->settings?->writing_style) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="brand_personality">Brand personality (comma-separated)</label>
                <input id="brand_personality" name="brand_personality" value="{{ $value('brand_personality', implode(', ', $current?->settings?->brand_personality ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="brand_values">Brand values (comma-separated)</label>
                <input id="brand_values" name="brand_values" value="{{ $value('brand_values', implode(', ', $current?->settings?->brand_values ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="brand_keywords">Brand keywords (comma-separated)</label>
                <input id="brand_keywords" name="brand_keywords" value="{{ $value('brand_keywords', implode(', ', $current?->settings?->brand_keywords ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="topics_to_avoid">Topics to avoid (comma-separated)</label>
                <input id="topics_to_avoid" name="topics_to_avoid" value="{{ $value('topics_to_avoid', implode(', ', $current?->settings?->topics_to_avoid ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="content_restrictions">Content restrictions (comma-separated)</label>
                <input id="content_restrictions" name="content_restrictions" value="{{ $value('content_restrictions', implode(', ', $current?->settings?->content_restrictions ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="hashtag_preferences">Hashtag preferences (comma-separated)</label>
                <input id="hashtag_preferences" name="hashtag_preferences" value="{{ $value('hashtag_preferences', implode(', ', $current?->settings?->hashtag_preferences ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="preferred_publishing_times">Preferred publishing times (comma-separated)</label>
                <input id="preferred_publishing_times" name="preferred_publishing_times" value="{{ $value('preferred_publishing_times', implode(', ', $current?->settings?->preferred_publishing_times ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="content_formats">Preferred formats (comma-separated)</label>
                <input id="content_formats" name="content_formats" value="{{ $value('content_formats', implode(', ', $current?->settings?->content_formats ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="brand_colours">Brand colours (comma-separated)</label>
                <input id="brand_colours" name="brand_colours" value="{{ $value('brand_colours', implode(', ', $current?->settings?->brand_colours ?? [])) }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="publishing_frequency">Publishing frequency</label>
                <input id="publishing_frequency" name="publishing_frequency" value="{{ $value('publishing_frequency', $current?->settings?->publishing_frequency ?? 'weekly') }}" style="{{ $inputStyle }}">
            </div>
            <div>
                <label class="muted" for="timezone">Timezone</label>
                <input id="timezone" name="timezone" value="{{ $value('timezone', $current?->settings?->timezone ?? 'UTC') }}" style="{{ $inputStyle }}">
            </div>
            <h2 style="grid-column:1/-1;">Objectives and content mix</h2>
            <fieldset style="grid-column:1/-1;border:1px solid rgba(148,163,184,.18);border-radius:12px;padding:1rem;">
                <legend class="muted">Marketing objectives</legend>
                <input type="hidden" name="marketing_objectives[]" value="">
                @foreach ($objectives as $key => $label)
                    <label style="display:inline-flex;gap:8px;margin:8px 16px 0 0;">
                        <input type="checkbox" name="marketing_objectives[]" value="{{ $key }}" @checked(in_array($key, $arrayValue('marketing_objectives', $current?->settings?->marketing_objectives ?? []), true))>
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>
            @foreach ($contentCategories as $key => $label)
                <div>
                    <label class="muted" for="content_preferences_{{ $key }}">{{ $label }} content preference (1-5)</label>
                    <select id="content_preferences_{{ $key }}" name="content_preferences[{{ $key }}]" style="{{ $inputStyle }}">
                        <option value="">Not set</option>
                        @for ($rating = 1; $rating <= 5; $rating++)
                            <option value="{{ $rating }}" @selected((string) $value("content_preferences.$key", $current?->settings?->content_preferences[$key] ?? '') === (string) $rating)>{{ $rating }}</option>
                        @endfor
                    </select>
                </div>
            @endforeach
        </div>
        @if ($errors->any())
            <div role="alert" style="margin-top:1rem;color:#fca5a5;">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:1.5rem;">
            <a href="{{ route('brands.index') }}" class="button-secondary">Cancel</a>
            <button type="submit" class="button">{{ $method === 'POST' ? 'Save brand' : 'Update brand' }}</button>
        </div>
    </form>
</section>
