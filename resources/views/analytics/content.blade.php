<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Analytics Studio · Content</p>
                <h1>Content Performance</h1>
                <p class="muted">Detailed engagement rates and reach metrics for published social posts.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.dashboard') }}" class="button-secondary">&larr; Back to Overview</a>
                <a href="{{ route('analytics.recommendations') }}" class="button">Optimization Recs</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.content') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.platforms') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Platforms</a>
        <a href="{{ route('analytics.recommendations') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    {{-- Brand Filter Dropdown --}}
    <div class="card panel" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <form method="GET" action="{{ route('analytics.content') }}" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <label for="brand_id" style="font-weight:600;font-size:0.88rem;">Filter by Brand:</label>
            <select name="brand_id" id="brand_id" onchange="this.form.submit()" style="padding:0.4rem 0.8rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.88rem;min-width:200px;">
                <option value="">All Brands</option>
                @foreach($brands as $b)
                    <option value="{{ $b->id }}" {{ $selectedBrand && $selectedBrand->id === $b->id ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
            @if($selectedBrand)
                <a href="{{ route('analytics.content') }}" class="muted" style="font-size:0.82rem;">Clear filter</a>
            @endif
        </form>
    </div>

    {{-- Top Performing Posts --}}
    <div class="card panel" style="margin-bottom:2rem;">
        <h2 style="margin-top:0;margin-bottom:1rem;font-size:1.15rem;">Top Performing Posts (Engagement Rate)</h2>
        @if($topPosts->isEmpty())
            <p class="muted" style="padding:1.5rem 0;text-align:center;">No high-engagement posts tracked yet.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Post ID</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Platform</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Engagement Rate</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Collected At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topPosts as $post)
                            <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                <td style="padding:0.6rem 0.75rem;font-family:monospace;font-size:0.82rem;">{{ $post->external_post_id ?? 'N/A' }}</td>
                                <td style="padding:0.6rem 0.75rem;text-transform:capitalize;">
                                    {{ $post->platform ?? ($post->socialAccount->provider ?? 'Social') }}
                                </td>
                                <td style="padding:0.6rem 0.75rem;font-weight:600;color:var(--success, #10b981);">
                                    {{ is_numeric($post->metric_value) ? number_format($post->metric_value, 2) : $post->metric_value }}%
                                </td>
                                <td style="padding:0.6rem 0.75rem;" class="muted">{{ $post->collected_at ? \Carbon\Carbon::parse($post->collected_at)->diffForHumans() : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Recent Posts Table --}}
    <div class="card panel">
        <h2 style="margin-top:0;margin-bottom:1rem;font-size:1.15rem;">Recent Post Analytics</h2>
        @if($recentPosts->isEmpty())
            <p class="muted" style="padding:1.5rem 0;text-align:center;">No recent post analytics collected.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Post ID</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Metric Name</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Value</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Account</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPosts as $post)
                            <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                <td style="padding:0.6rem 0.75rem;font-family:monospace;font-size:0.82rem;">{{ $post->external_post_id ?? 'N/A' }}</td>
                                <td style="padding:0.6rem 0.75rem;">{{ str_replace('_', ' ', $post->metric_name) }}</td>
                                <td style="padding:0.6rem 0.75rem;font-weight:600;">
                                    {{ is_numeric($post->metric_value) ? number_format($post->metric_value, is_float($post->metric_value + 0) && floor($post->metric_value) != $post->metric_value ? 2 : 0) : $post->metric_value }}
                                </td>
                                <td style="padding:0.6rem 0.75rem;">Account #{{ $post->social_account_id }}</td>
                                <td style="padding:0.6rem 0.75rem;" class="muted">{{ $post->collected_at ? \Carbon\Carbon::parse($post->collected_at)->diffForHumans() : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
