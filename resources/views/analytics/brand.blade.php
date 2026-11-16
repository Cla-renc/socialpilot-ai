<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Analytics Studio · Brand Insights</p>
                <h1>{{ $brand->name }} Analytics</h1>
                <p class="muted">Performance snapshots and top performing content for {{ $brand->name }}.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.dashboard') }}" class="button-secondary">&larr; Back to Overview</a>
                <a href="{{ route('analytics.content', ['brand_id' => $brand->id]) }}" class="button">Content Details</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.brand', $brand) }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">{{ $brand->name }}</a>
        <a href="{{ route('analytics.content', ['brand_id' => $brand->id]) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience', ['brand_id' => $brand->id]) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.recommendations') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    {{-- Snapshots grouped by metric_name --}}
    <h2 style="font-size:1.2rem;margin-bottom:1rem;">Metric Snapshots</h2>
    @if($snapshots->isEmpty())
        <div class="card panel" style="margin-bottom:2rem;text-align:center;padding:2rem;">
            <p class="muted">No analytics snapshots recorded yet for this brand.</p>
        </div>
    @else
        <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
            @foreach($snapshots as $metricName => $records)
                @php
                    $latest = $records->first();
                @endphp
                <div class="card panel">
                    <span class="muted" style="font-size:0.82rem;text-transform:uppercase;letter-spacing:0.04em;">{{ str_replace('_', ' ', $metricName) }}</span>
                    <div style="font-size:1.85rem;font-weight:700;margin-top:0.35rem;">
                        {{ is_numeric($latest->metric_value) ? number_format($latest->metric_value, is_float($latest->metric_value + 0) && floor($latest->metric_value) != $latest->metric_value ? 2 : 0) : $latest->metric_value }}
                    </div>
                    <div class="muted" style="font-size:0.75rem;margin-top:0.35rem;">
                        {{ $records->count() }} records · Latest {{ $latest->collected_at ? \Carbon\Carbon::parse($latest->collected_at)->diffForHumans() : '' }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Top Performing Posts --}}
    <div class="card panel" style="margin-bottom:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h2 style="margin:0;font-size:1.15rem;">Top Performing Posts (Engagement Rate)</h2>
            <a href="{{ route('analytics.content', ['brand_id' => $brand->id]) }}" style="font-size:0.85rem;">View all content &rarr;</a>
        </div>

        @if($topPosts->isEmpty())
            <p class="muted" style="padding:1.5rem 0;text-align:center;">No post analytics recorded yet for this brand's accounts.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Post ID</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Account</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Metric</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Value</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topPosts as $post)
                            <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                <td style="padding:0.6rem 0.75rem;font-family:monospace;font-size:0.82rem;">{{ $post->external_post_id ?? 'N/A' }}</td>
                                <td style="padding:0.6rem 0.75rem;">Account #{{ $post->social_account_id }}</td>
                                <td style="padding:0.6rem 0.75rem;">{{ str_replace('_', ' ', $post->metric_name) }}</td>
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
</x-app-layout>
