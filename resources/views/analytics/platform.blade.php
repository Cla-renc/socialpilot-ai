<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Analytics Studio · Platforms</p>
                <h1>Platform Breakdown</h1>
                <p class="muted">Compare metrics across social media channels and connected accounts.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.dashboard') }}" class="button-secondary">&larr; Back to Overview</a>
                <a href="{{ route('analytics.content') }}" class="button">Content Performance</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.content') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.platforms') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Platforms</a>
        <a href="{{ route('analytics.recommendations') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    {{-- Platform filter tabs --}}
    @php
        $standardPlatforms = ['all', 'facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'];
        $allPlatformOptions = collect($standardPlatforms)->merge($platforms)->unique();
    @endphp
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        @foreach($allPlatformOptions as $p)
            <a href="{{ route('analytics.platforms', ['platform' => $p]) }}" 
               class="{{ $platform === $p ? 'button' : 'button-secondary' }}" 
               style="padding:0.35rem 0.85rem;font-size:0.82rem;text-transform:capitalize;">
                {{ $p }}
            </a>
        @endforeach
    </div>

    {{-- Snapshots grouped by platform --}}
    @if($snapshots->isEmpty())
        <div class="card panel" style="text-align:center;padding:2.5rem;">
            <p class="muted">No platform metrics found for the selected filter.</p>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            @foreach($snapshots as $platName => $records)
                <div class="card panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid var(--stroke, #e5e7eb);padding-bottom:0.75rem;">
                        <h2 style="margin:0;font-size:1.15rem;text-transform:capitalize;">{{ $platName }} Performance</h2>
                        <span class="muted" style="font-size:0.82rem;">{{ $records->count() }} snapshot(s)</span>
                    </div>

                    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                        @foreach($records->groupBy('metric_name') as $mName => $mRecords)
                            @php $latest = $mRecords->first(); @endphp
                            <div style="padding:0.75rem;border:1px solid var(--stroke, #e5e7eb);border-radius:8px;">
                                <span class="muted" style="font-size:0.78rem;text-transform:uppercase;">{{ str_replace('_', ' ', $mName) }}</span>
                                <div style="font-size:1.5rem;font-weight:700;margin-top:0.25rem;">
                                    {{ is_numeric($latest->metric_value) ? number_format($latest->metric_value, is_float($latest->metric_value + 0) && floor($latest->metric_value) != $latest->metric_value ? 2 : 0) : $latest->metric_value }}
                                </div>
                                <div class="muted" style="font-size:0.75rem;margin-top:0.25rem;">
                                    {{ $latest->collected_at ? \Carbon\Carbon::parse($latest->collected_at)->diffForHumans() : '' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
