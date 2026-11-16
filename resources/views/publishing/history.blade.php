<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing Studio · Phase 9</p>
                <h1>Publishing History</h1>
                <p class="muted">Complete chronological log of all social media publishing attempts across your channels.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('publishing.publish-now') }}" class="button">Publish Now</a>
                <a href="{{ route('publishing.dashboard') }}" class="button-secondary">Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('publishing.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('publishing.publish-now') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Publish Now</a>
        <a href="{{ route('publishing.history') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">History</a>
        <a href="{{ route('publishing.failed') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Failed Attempts</a>
        <a href="{{ route('publishing.notifications') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Notifications</a>
    </div>

    {{-- Filter Form --}}
    <div class="card panel" style="margin-bottom:1.5rem;padding:1.25rem;">
        <form method="GET" action="{{ route('publishing.history') }}" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="display:flex;flex-direction:column;gap:0.35rem;min-width:180px;">
                <label for="brand_id" style="font-size:0.82rem;font-weight:600;color:var(--muted);">Brand</label>
                <select name="brand_id" id="brand_id" style="padding:0.5rem 0.75rem;border-radius:8px;background:var(--surface);border:1px solid var(--stroke);color:var(--text);font-size:0.88rem;">
                    <option value="">All Brands</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:0.35rem;min-width:160px;">
                <label for="platform" style="font-size:0.82rem;font-weight:600;color:var(--muted);">Platform</label>
                <select name="platform" id="platform" style="padding:0.5rem 0.75rem;border-radius:8px;background:var(--surface);border:1px solid var(--stroke);color:var(--text);font-size:0.88rem;">
                    <option value="">All Platforms</option>
                    @foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'twitter' => 'X / Twitter'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;flex-direction:column;gap:0.35rem;min-width:160px;">
                <label for="status" style="font-size:0.82rem;font-weight:600;color:var(--muted);">Status</label>
                <select name="status" id="status" style="padding:0.5rem 0.75rem;border-radius:8px;background:var(--surface);border:1px solid var(--stroke);color:var(--text);font-size:0.88rem;">
                    <option value="">All Statuses</option>
                    @foreach(['published' => 'Published', 'failed' => 'Failed', 'processing' => 'Processing', 'publishing' => 'Publishing', 'partially_published' => 'Partially Published', 'cancelled' => 'Cancelled'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="button" style="padding:0.5rem 1.25rem;">Filter</button>
                @if(request()->hasAny(['brand_id', 'platform', 'status']))
                    <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.5rem 1rem;">Reset</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Attempts Table --}}
    <div class="card panel" style="padding:1.5rem;">
        @if($attempts->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;" class="muted">
                <p style="font-size:1rem;margin-bottom:1rem;">No publishing attempts match your criteria.</p>
                @if(request()->hasAny(['brand_id', 'platform', 'status']))
                    <a href="{{ route('publishing.history') }}" class="button-secondary">Clear Filters</a>
                @else
                    <a href="{{ route('publishing.publish-now') }}" class="button">Create Post Now</a>
                @endif
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--stroke);color:var(--muted);text-transform:uppercase;font-size:0.75rem;letter-spacing:0.05em;">
                            <th style="padding:0.75rem 1rem;">Platform</th>
                            <th style="padding:0.75rem 1rem;">Status</th>
                            <th style="padding:0.75rem 1rem;">External Post URL</th>
                            <th style="padding:0.75rem 1rem;">Attempt #</th>
                            <th style="padding:0.75rem 1rem;">Published At</th>
                            <th style="padding:0.75rem 1rem;">Error</th>
                            <th style="padding:0.75rem 1rem;text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attempts as $attempt)
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
                                            {{ $attempt->external_post_url }}
                                        </a>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="padding:0.75rem 1rem;color:var(--muted);">
                                    #{{ $attempt->attempt_number ?? 1 }}
                                </td>
                                <td style="padding:0.75rem 1rem;white-space:nowrap;color:var(--muted);font-size:0.85rem;">
                                    {{ $attempt->published_at ? $attempt->published_at->format('M j, Y g:i A') : ($attempt->created_at ? $attempt->created_at->format('M j, Y g:i A') : '—') }}
                                </td>
                                <td style="padding:0.75rem 1rem;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--danger, #f87171);font-size:0.85rem;">
                                    {{ $attempt->error_message ?: '—' }}
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

            <div style="margin-top:1.5rem;">
                {{ $attempts->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
