<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Analytics Studio · AI Insights</p>
                <h1>Optimization Recommendations</h1>
                <p class="muted">AI-generated recommendations to maximize engagement, posting frequency, and reach.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.dashboard') }}" class="button-secondary">&larr; Back to Overview</a>
                <a href="{{ route('analytics.reports') }}" class="button">Reports</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.content') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.platforms') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Platforms</a>
        <a href="{{ route('analytics.recommendations') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    {{-- Status filter tabs --}}
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        @foreach(['pending' => 'Pending', 'accepted' => 'Accepted', 'dismissed' => 'Dismissed', 'all' => 'All'] as $val => $label)
            <a href="{{ route('analytics.recommendations', ['status' => $val]) }}" 
               class="{{ $status === $val ? 'button' : 'button-secondary' }}" 
               style="padding:0.35rem 0.85rem;font-size:0.82rem;">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Recommendations list --}}
    @if($recs->isEmpty())
        <div class="card panel" style="text-align:center;padding:2.5rem;">
            <p class="muted">No {{ $status !== 'all' ? $status : '' }} recommendations found.</p>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:2rem;">
            @foreach($recs as $rec)
                <div class="card panel" id="rec-card-{{ $rec->id }}" style="display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                        <div>
                            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                <span class="badge" style="padding:0.25rem 0.6rem;border-radius:12px;font-size:0.78rem;background:rgba(59,130,246,0.12);color:#2563eb;text-transform:uppercase;">
                                    {{ str_replace('_', ' ', $rec->recommendation_type) }}
                                </span>
                                @if($rec->priority)
                                    <span class="badge" style="padding:0.2rem 0.5rem;border-radius:8px;font-size:0.72rem;
                                        background:{{ $rec->priority === 'high' ? 'rgba(239,68,68,0.12)' : ($rec->priority === 'medium' ? 'rgba(245,158,11,0.12)' : 'rgba(107,114,128,0.12)') }};
                                        color:{{ $rec->priority === 'high' ? '#dc2626' : ($rec->priority === 'medium' ? '#d97706' : '#4b5563') }};text-transform:capitalize;">
                                        {{ $rec->priority }} Priority
                                    </span>
                                @endif
                                @if($rec->confidence)
                                    <span class="muted" style="font-size:0.78rem;">
                                        Confidence: <strong>{{ round($rec->confidence * 100) }}%</strong>
                                    </span>
                                @endif
                            </div>
                            <p style="margin:0.5rem 0 0.25rem 0;font-size:0.95rem;line-height:1.5;">
                                {{ $rec->description ?? $rec->recommendation }}
                            </p>
                            @if($rec->brand)
                                <div class="muted" style="font-size:0.78rem;">Brand: <strong>{{ $rec->brand->name }}</strong></div>
                            @endif
                        </div>

                        <div style="display:flex;align-items:center;gap:0.5rem;" id="rec-actions-{{ $rec->id }}">
                            @if($rec->status === 'pending')
                                <button type="button" onclick="updateRecStatus('{{ $rec->id }}', 'accept')" class="button" style="padding:0.35rem 0.75rem;font-size:0.8rem;background:#10b981;border-color:#10b981;">
                                    Accept
                                </button>
                                <button type="button" onclick="updateRecStatus('{{ $rec->id }}', 'dismiss')" class="button-secondary" style="padding:0.35rem 0.75rem;font-size:0.8rem;color:#ef4444;">
                                    Dismiss
                                </button>
                            @else
                                <span style="padding:0.25rem 0.6rem;border-radius:12px;font-size:0.78rem;
                                    background:{{ $rec->status === 'accepted' ? 'rgba(16,185,129,0.12)' : 'rgba(107,114,128,0.12)' }};
                                    color:{{ $rec->status === 'accepted' ? '#059669' : '#6b7280' }};text-transform:capitalize;">
                                    {{ $rec->status }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:1rem;">
            {{ $recs->appends(['status' => $status])->links() }}
        </div>
    @endif

    <script>
    function updateRecStatus(recId, action) {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch(`/api/v1/analytics/recommendations/${recId}/${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || ''
            }
        })
        .then(response => {
            if (response.ok) {
                const actionsDiv = document.getElementById(`rec-actions-${recId}`);
                if (actionsDiv) {
                    actionsDiv.innerHTML = `<span style="padding:0.25rem 0.6rem;border-radius:12px;font-size:0.78rem;background:rgba(16,185,129,0.12);color:#059669;">${action === 'accept' ? 'Accepted' : 'Dismissed'}</span>`;
                }
            } else {
                alert('Failed to update recommendation. Please try again.');
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred.');
        });
    }
    </script>
</x-app-layout>
