<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Social Media Publishing · Phase 9</p>
                <h1>Publishing Studio</h1>
                <p class="muted">Monitor, manage, and dispatch live posts across all connected social channels.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('publishing.publish-now') }}" class="button">Publish Now</a>
                <a href="{{ route('publishing.history') }}" class="button-secondary">View History</a>
                <a href="{{ route('publishing.failed') }}" class="button-secondary">View Failed</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('publishing.dashboard') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('publishing.publish-now') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Publish Now</a>
        <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">History</a>
        <a href="{{ route('publishing.failed') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Failed Attempts</a>
        <a href="{{ route('publishing.notifications') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Notifications</a>
    </div>

    {{-- Brand Filter if brands exist --}}
    @if($brands->isNotEmpty())
        <div class="card panel" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
            <form method="GET" action="{{ route('publishing.dashboard') }}" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <label for="brand_id" style="font-size:0.9rem;font-weight:600;color:var(--text);">Select Brand:</label>
                <select name="brand_id" id="brand_id" onchange="this.form.submit()" style="padding:0.45rem 1rem;border-radius:8px;background:var(--surface);border:1px solid var(--stroke);color:var(--text);font-size:0.9rem;">
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected($selectedBrandId == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif

    {{-- 4 Stats Cards in a row --}}
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
        <div class="card panel" style="padding:1.25rem;">
            <span class="muted" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Total Posts</span>
            <div style="font-size:2.2rem;font-weight:700;margin-top:0.4rem;color:var(--text);">{{ number_format($stats['total']) }}</div>
        </div>
        <div class="card panel" style="padding:1.25rem;">
            <span class="muted" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Published</span>
            <div style="font-size:2.2rem;font-weight:700;margin-top:0.4rem;color:#34d399;">{{ number_format($stats['published']) }}</div>
        </div>
        <div class="card panel" style="padding:1.25rem;">
            <span class="muted" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Failed</span>
            <div style="font-size:2.2rem;font-weight:700;margin-top:0.4rem;color:#f87171;">{{ number_format($stats['failed']) }}</div>
        </div>
        <div class="card panel" style="padding:1.25rem;">
            <span class="muted" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Processing</span>
            <div style="font-size:2.2rem;font-weight:700;margin-top:0.4rem;color:#fbbf24;">{{ number_format($stats['processing']) }}</div>
        </div>
    </div>

    {{-- Recent Attempts Table --}}
    <div class="card panel" style="padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
            <h2 style="margin:0;font-size:1.2rem;font-weight:700;">Recent Publishing Attempts</h2>
            <div style="display:flex;gap:0.5rem;">
                <a href="{{ route('publishing.history') }}" class="button-secondary" style="font-size:0.85rem;padding:0.4rem 0.8rem;">View All History &rarr;</a>
            </div>
        </div>

        @if($recentAttempts->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;" class="muted">
                <p style="font-size:1rem;margin-bottom:1rem;">No recent publishing attempts found.</p>
                <a href="{{ route('publishing.publish-now') }}" class="button">Create Your First Post</a>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--stroke);color:var(--muted);text-transform:uppercase;font-size:0.75rem;letter-spacing:0.05em;">
                            <th style="padding:0.75rem 1rem;">Platform</th>
                            <th style="padding:0.75rem 1rem;">Status</th>
                            <th style="padding:0.75rem 1rem;">External URL</th>
                            <th style="padding:0.75rem 1rem;">Error Message</th>
                            <th style="padding:0.75rem 1rem;">Created At</th>
                            <th style="padding:0.75rem 1rem;text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAttempts as $attempt)
                            @php
                                $statusStyle = match($attempt->status) {
                                    'published' => ['bg' => 'rgba(52, 211, 153, 0.15)', 'color' => '#34d399', 'label' => 'Published'],
                                    'failed' => ['bg' => 'rgba(248, 113, 113, 0.15)', 'color' => '#f87171', 'label' => 'Failed'],
                                    'processing' => ['bg' => 'rgba(251, 191, 36, 0.15)', 'color' => '#fbbf24', 'label' => 'Processing'],
                                    'publishing' => ['bg' => 'rgba(251, 191, 36, 0.15)', 'color' => '#fbbf24', 'label' => 'Publishing'],
                                    'partially_published' => ['bg' => 'rgba(251, 146, 60, 0.15)', 'color' => '#fb923c', 'label' => 'Partially Published'],
                                    'cancelled' => ['bg' => 'rgba(148, 163, 184, 0.15)', 'color' => '#94a3b8', 'label' => 'Cancelled'],
                                    default => ['bg' => 'rgba(139, 92, 246, 0.15)', 'color' => '#a78bfa', 'label' => ucfirst($attempt->status ?? 'Unknown')],
                                };
                            @endphp
                            <tr style="border-bottom:1px solid var(--stroke);">
                                <td style="padding:0.75rem 1rem;">
                                    <span style="display:inline-block;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.8rem;font-weight:600;background:rgba(255,255,255,0.06);text-transform:capitalize;">
                                        {{ $attempt->platform }}
                                    </span>
                                </td>
                                <td style="padding:0.75rem 1rem;">
                                    <span style="display:inline-block;padding:0.25rem 0.65rem;border-radius:999px;font-size:0.75rem;font-weight:700;background:{{ $statusStyle['bg'] }};color:{{ $statusStyle['color'] }};">
                                        {{ $statusStyle['label'] }}
                                    </span>
                                </td>
                                <td style="padding:0.75rem 1rem;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    @if($attempt->external_post_url)
                                        <a href="{{ $attempt->external_post_url }}" target="_blank" rel="noopener noreferrer" style="color:var(--primary-2, #22d3ee);text-decoration:underline;">
                                            View Post &nearr;
                                        </a>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="padding:0.75rem 1rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--danger, #f87171);">
                                    {{ $attempt->error_message ?: '—' }}
                                </td>
                                <td style="padding:0.75rem 1rem;white-space:nowrap;color:var(--muted);font-size:0.82rem;">
                                    {{ $attempt->created_at ? $attempt->created_at->format('M j, Y g:i A') : '—' }}
                                </td>
                                <td style="padding:0.75rem 1rem;text-align:right;">
                                    <a href="{{ route('publishing.show', $attempt) }}" class="button-secondary" style="padding:0.3rem 0.65rem;font-size:0.8rem;">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
