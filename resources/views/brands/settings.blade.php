<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Brand settings</p>
                <h1>{{ $brand->name }}</h1>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Brand details</a>
                <a href="{{ route('brands.edit', $brand) }}" class="button-secondary">Edit profile</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="card panel" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card panel" style="max-width:900px;margin:0 auto;">
        <form method="POST" action="{{ route('brands.settings.update', $brand) }}">
            @csrf
            @method('PUT')
            <div class="dashboard-grid" style="grid-template-columns:repeat(2,minmax(220px,1fr));">
                @foreach ([
                    'brand_voice' => 'Brand voice',
                    'writing_style' => 'Writing style',
                    'brand_personality' => 'Personality (comma-separated)',
                    'brand_values' => 'Values (comma-separated)',
                    'brand_keywords' => 'Keywords (comma-separated)',
                    'topics_to_avoid' => 'Topics to avoid (comma-separated)',
                    'content_restrictions' => 'Content restrictions (comma-separated)',
                    'hashtag_preferences' => 'Hashtag preferences (comma-separated)',
                    'content_formats' => 'Preferred formats (comma-separated)',
                    'brand_colours' => 'Brand colours (comma-separated)',
                    'preferred_publishing_times' => 'Preferred publishing times (comma-separated)',
                    'publishing_frequency' => 'Publishing frequency',
                    'timezone' => 'Timezone',
                ] as $field => $label)
                    @php
                        $fieldValue = old($field, $brand->settings?->{$field});
                        if (is_array($fieldValue)) $fieldValue = implode(', ', $fieldValue);
                    @endphp
                    <div>
                        <label class="muted" for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" value="{{ $fieldValue }}" style="width:100%;margin-top:8px;padding:.8rem .9rem;border-radius:12px;border:1px solid rgba(148,163,184,.18);background:rgba(15,23,42,.6);color:#e2e8f0;">
                    </div>
                @endforeach
                <fieldset style="grid-column:1/-1;border:1px solid rgba(148,163,184,.18);border-radius:12px;padding:1rem;">
                    <legend class="muted">Marketing objectives</legend>
                    <input type="hidden" name="marketing_objectives[]" value="">
                    @foreach ($objectives as $key => $label)
                        <label style="display:inline-flex;gap:8px;margin:8px 16px 0 0;">
                            <input type="checkbox" name="marketing_objectives[]" value="{{ $key }}" @checked(in_array($key, old('marketing_objectives', $brand->settings?->marketing_objectives ?? []), true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </fieldset>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:1.5rem;">
                <button type="submit" class="button">Save settings</button>
            </div>
        </form>
    </section>
</x-app-layout>
