<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Brand profile</p>
                <h1>{{ $brand->name }}</h1>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.content.studio', $brand) }}" class="button">Content Studio</a>
                <a href="{{ route('brands.trends.index', $brand) }}" class="button-secondary">Trend intelligence</a>
                <a href="{{ route('brands.social-accounts.index', $brand) }}" class="button-secondary">Social accounts</a>
                <a href="{{ route('brands.settings', $brand) }}" class="button-secondary">Settings</a>
                <a href="{{ route('brands.collaboration', $brand) }}" class="button-secondary">Collaboration</a>
                <a href="{{ route('brands.edit', $brand) }}" class="button-secondary">Edit</a>
            </div>
        </div>
    </x-slot>

    <section class="content-grid">
        <article class="card panel">
            <h2>Core details</h2>
            <div class="list">
                <div class="line-item"><span class="muted">Category</span><strong>{{ $brand->category ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Niche</span><strong>{{ $brand->niche ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Website</span><strong>{{ $brand->website_url ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Audience</span><strong>{{ $brand->audience ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Age group</span><strong>{{ $brand->target_age_group ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Audience interests</span><strong>{{ implode(', ', $brand->audience_interests ?? []) ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Region</span><strong>{{ $brand->region ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Language</span><strong>{{ $brand->language ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Tone</span><strong>{{ $brand->tone ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Status</span><strong>{{ ucfirst($brand->status) }}</strong></div>
            </div>
        </article>

        <article class="card panel">
            <h2>AI settings</h2>
            <div class="list">
                <div class="line-item"><span class="muted">Brand voice</span><strong>{{ $brand->settings?->brand_voice ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Writing style</span><strong>{{ $brand->settings?->writing_style ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Formats</span><strong>{{ is_array($brand->settings?->content_formats) ? implode(', ', $brand->settings->content_formats) : ($brand->settings?->content_formats ?: 'Not set') }}</strong></div>
                <div class="line-item"><span class="muted">Frequency</span><strong>{{ $brand->settings?->publishing_frequency ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Timezone</span><strong>{{ $brand->settings?->timezone ?: 'Not set' }}</strong></div>
                <div class="line-item"><span class="muted">Objectives</span><strong>{{ implode(', ', array_map(fn ($key) => config('brand.objectives.'.$key, $key), $brand->settings?->marketing_objectives ?? [])) ?: 'Not set' }}</strong></div>
            </div>
        </article>
        <article class="card panel">
            <h2>Overview</h2>
            <div class="list">
                <div class="line-item"><span class="muted">Connected accounts</span><strong>{{ $brand->connected_accounts_count }}</strong></div>
                <div class="line-item"><span class="muted">Drafts</span><strong>{{ $brand->content_drafts_count }}</strong></div>
                <div class="line-item"><span class="muted">Posts</span><strong>{{ $brand->posts_count }}</strong></div>
                <div class="line-item"><span class="muted">Campaigns</span><strong>{{ $brand->campaigns_count }}</strong></div>
            </div>
        </article>
    </section>
</x-app-layout>
