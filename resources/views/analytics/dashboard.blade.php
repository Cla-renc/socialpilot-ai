<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Data & Insights · Phase 10</p>
                <h1>Analytics Studio</h1>
                <p class="muted">Track performance, audience insights, and AI-powered recommendations across all your brands.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.content') }}" class="button">Content Performance</a>
                <a href="{{ route('analytics.recommendations') }}" class="button-secondary">AI Recommendations</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.content') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.platforms') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Platforms</a>
        <a href="{{ route('analytics.recommendations') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    {{-- Stats Grid --}}
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;">
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Total Snapshots</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;">{{ $stats['total_snapshots'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Posts Tracked</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;">{{ $stats['total_posts_tracked'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Pending AI Recs</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;color:var(--warning, #f59e0b);">{{ $stats['pending_recommendations'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Brands Connected</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;">{{ $stats['brands_connected'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Social Accounts</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;color:var(--success, #10b981);">{{ $stats['accounts_connected'] }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        {{-- Recent Collection Logs --}}
        <div class="card panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h2 style="margin:0;font-size:1.15rem;">Recent Collection Logs</h2>
                <span class="muted" style="font-size:0.82rem;">Last 5 runs</span>
            </div>
            @if($recentLogs->isEmpty())
                <p class="muted" style="padding:1.5rem 0;text-align:center;">No collection logs yet. Trigger collection via the API: <code>POST /api/v1/analytics/collect</code></p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Account</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Status</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Started</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Records</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLogs as $log)
                                <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                    <td style="padding:0.6rem 0.75rem;">{{ $log->social_account_id }}</td>
                                    <td style="padding:0.6rem 0.75rem;">
                                        <span style="padding:0.2rem 0.6rem;border-radius:12px;font-size:0.78rem;
                                            background:{{ $log->status === 'completed' ? 'rgba(16,185,129,0.12)' : ($log->status === 'failed' ? 'rgba(239,68,68,0.12)' : 'rgba(245,158,11,0.12)') }};
                                            color:{{ $log->status === 'completed' ? '#059669' : ($log->status === 'failed' ? '#dc2626' : '#d97706') }};">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td style="padding:0.6rem 0.75rem;" class="muted">{{ $log->started_at ? \Carbon\Carbon::parse($log->started_at)->diffForHumans() : '—' }}</td>
                                    <td style="padding:0.6rem 0.75rem;">{{ $log->records_collected ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div style="margin-top:1rem;padding:0.75rem;background:rgba(59,130,246,0.06);border-radius:8px;font-size:0.83rem;" class="muted">
                💡 <strong>Trigger Collection:</strong> <code>POST /api/v1/analytics/collect</code> — dispatches collection jobs for all your brand accounts.
            </div>
        </div>

        {{-- Brands sidebar --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">Brands</h3>
                @forelse($brands as $brand)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--stroke, #e5e7eb);">
                        <div>
                            <span style="font-weight:500;">{{ $brand->name }}</span>
                            <div class="muted" style="font-size:0.78rem;">{{ $brand->socialAccounts->count() }} account(s)</div>
                        </div>
                        <a href="{{ route('analytics.brand', $brand) }}" class="button-secondary" style="font-size:0.78rem;padding:0.25rem 0.6rem;">View →</a>
                    </div>
                @empty
                    <p class="muted">No brands found. <a href="{{ route('brands.create') }}">Create a brand</a> to get started.</p>
                @endforelse
            </div>

            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">Quick Links</h3>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <a href="{{ route('analytics.content') }}" class="button-secondary" style="text-align:center;">Content Performance</a>
                    <a href="{{ route('analytics.audience') }}" class="button-secondary" style="text-align:center;">Audience Insights</a>
                    <a href="{{ route('analytics.platforms') }}" class="button-secondary" style="text-align:center;">Platform Breakdown</a>
                    <a href="{{ route('analytics.recommendations') }}" class="button" style="text-align:center;">AI Recommendations</a>
                    <a href="{{ route('analytics.reports') }}" class="button-secondary" style="text-align:center;">Generate Reports</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
