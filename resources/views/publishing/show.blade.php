<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing Studio · Attempt #{{ $attempt->id }}</p>
                <h1>Publishing Attempt Details</h1>
                <p class="muted">Detailed diagnostic inspection and metadata for publishing attempt #{{ $attempt->id }}.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('publishing.history') }}" class="button-secondary">&larr; Back to History</a>
                @if($attempt->status === 'failed')
                    <a href="{{ route('publishing.failed') }}" class="button-secondary">View Failed List</a>
                @endif
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('publishing.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('publishing.publish-now') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Publish Now</a>
        <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">History</a>
        <a href="{{ route('publishing.failed') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Failed Attempts</a>
        <a href="{{ route('publishing.notifications') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Notifications</a>
    </div>

    @php
        $statusStyle = match($attempt->status) {
            'published' => ['bg' => 'rgba(52, 211, 153, 0.15)', 'color' => '#34d399', 'border' => '#34d399', 'label' => 'Published'],
            'failed' => ['bg' => 'rgba(248, 113, 113, 0.15)', 'color' => '#f87171', 'border' => '#f87171', 'label' => 'Failed'],
            'processing' => ['bg' => 'rgba(251, 191, 36, 0.15)', 'color' => '#fbbf24', 'border' => '#fbbf24', 'label' => 'Processing'],
            'publishing' => ['bg' => 'rgba(251, 191, 36, 0.15)', 'color' => '#fbbf24', 'border' => '#fbbf24', 'label' => 'Publishing'],
            'partially_published' => ['bg' => 'rgba(251, 146, 60, 0.15)', 'color' => '#fb923c', 'border' => '#fb923c', 'label' => 'Partially Published'],
            'cancelled' => ['bg' => 'rgba(148, 163, 184, 0.15)', 'color' => '#94a3b8', 'border' => '#94a3b8', 'label' => 'Cancelled'],
            default => ['bg' => 'rgba(139, 92, 246, 0.15)', 'color' => '#a78bfa', 'border' => '#a78bfa', 'label' => ucfirst($attempt->status ?? 'Unknown')],
        };
    @endphp

    <div class="card panel" style="padding:2rem;max-width:900px;margin:0 auto 2rem;">
        {{-- Large Status Badge Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--stroke);padding-bottom:1.5rem;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
            <div>
                <span style="display:inline-block;padding:0.4rem 1.25rem;border-radius:999px;font-size:0.95rem;font-weight:700;background:{{ $statusStyle['bg'] }};color:{{ $statusStyle['color'] }};border:1px solid {{ $statusStyle['border'] }};letter-spacing:0.03em;">
                    {{ $statusStyle['label'] }}
                </span>
                <span style="margin-left:0.75rem;font-size:0.95rem;color:var(--muted);">Attempt ID: #{{ $attempt->id }}</span>
            </div>

            <div style="display:flex;gap:0.75rem;">
                @if($attempt->is_retryable && $attempt->status === 'failed')
                    <button type="button" onclick="retryAttempt({{ $attempt->id }}, this)" class="button" style="padding:0.45rem 1rem;">
                        Retry Publish
                    </button>
                @endif
                <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.45rem 1rem;">
                    &larr; Back to History
                </a>
            </div>
        </div>

        {{-- Detail Rows --}}
        <div style="display:grid;grid-template-columns:1fr;gap:1rem;">
            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Platform</span>
                <span style="font-weight:600;text-transform:capitalize;color:var(--text);">{{ $attempt->platform }}</span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Status</span>
                <span style="font-weight:600;color:{{ $statusStyle['color'] }};">{{ ucfirst($attempt->status) }}</span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Attempt Number</span>
                <span style="color:var(--text);">#{{ $attempt->attempt_number ?? 1 }}</span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">External Post ID</span>
                <span style="font-family:monospace;color:var(--text);word-break:break-all;">{{ $attempt->external_post_id ?: '—' }}</span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">External Post URL</span>
                <span>
                    @if($attempt->external_post_url)
                        <a href="{{ $attempt->external_post_url }}" target="_blank" rel="noopener noreferrer" style="color:var(--primary-2, #22d3ee);text-decoration:underline;word-break:break-all;">
                            {{ $attempt->external_post_url }} &nearr;
                        </a>
                    @else
                        <span class="muted">—</span>
                    @endif
                </span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Error Code</span>
                <span style="font-family:monospace;color:var(--warning, #fbbf24);">{{ $attempt->error_code ?: '—' }}</span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Error Message</span>
                <span style="color:var(--danger, #f87171);text-align:right;max-width:550px;word-break:break-word;">
                    {{ $attempt->error_message ?: '—' }}
                </span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Is Retryable</span>
                <span>
                    @if($attempt->is_retryable)
                        <span style="display:inline-block;padding:0.2rem 0.65rem;border-radius:999px;font-size:0.75rem;font-weight:700;background:rgba(52, 211, 153, 0.15);color:#34d399;">
                            Yes
                        </span>
                    @else
                        <span style="display:inline-block;padding:0.2rem 0.65rem;border-radius:999px;font-size:0.75rem;font-weight:700;background:rgba(148, 163, 184, 0.15);color:#94a3b8;">
                            No
                        </span>
                    @endif
                </span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Published At</span>
                <span style="color:var(--text);">
                    {{ $attempt->published_at ? $attempt->published_at->format('M j, Y g:i:s A') : '—' }}
                </span>
            </div>

            <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                <span class="muted" style="font-weight:600;min-width:180px;">Created At</span>
                <span style="color:var(--text);">
                    {{ $attempt->created_at ? $attempt->created_at->format('M j, Y g:i:s A') : '—' }}
                </span>
            </div>

            @if($attempt->completed_at)
                <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--stroke);gap:1rem;">
                    <span class="muted" style="font-weight:600;min-width:180px;">Completed At</span>
                    <span style="color:var(--text);">
                        {{ $attempt->completed_at->format('M j, Y g:i:s A') }}
                    </span>
                </div>
            @endif
        </div>

        {{-- Metadata Dumps if present --}}
        @if(!empty($attempt->request_metadata) || !empty($attempt->response_metadata))
            <div style="margin-top:2rem;">
                <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:1rem;">Diagnostic Payloads</h3>
                
                @if(!empty($attempt->request_metadata))
                    <div style="margin-bottom:1rem;">
                        <span class="muted" style="font-size:0.82rem;font-weight:600;text-transform:uppercase;">Request Metadata</span>
                        <pre style="background:var(--surface);padding:1rem;border-radius:8px;font-size:0.8rem;overflow-x:auto;color:var(--text);border:1px solid var(--stroke);margin-top:0.4rem;">{{ json_encode($attempt->request_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif

                @if(!empty($attempt->response_metadata))
                    <div>
                        <span class="muted" style="font-size:0.82rem;font-weight:600;text-transform:uppercase;">Response Metadata</span>
                        <pre style="background:var(--surface);padding:1rem;border-radius:8px;font-size:0.8rem;overflow-x:auto;color:var(--text);border:1px solid var(--stroke);margin-top:0.4rem;">{{ json_encode($attempt->response_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif
            </div>
        @endif

        <div style="margin-top:2rem;text-align:right;">
            <a href="{{ route('publishing.history') }}" class="button-secondary">Back to History</a>
        </div>
    </div>

    @if($attempt->is_retryable && $attempt->status === 'failed')
        <script>
            async function retryAttempt(attemptId, btn) {
                if (!confirm('Are you sure you want to retry this publishing attempt?')) {
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Retrying...';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

                try {
                    const response = await fetch(`/api/v1/publishing/attempts/${attemptId}/retry`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });

                    const data = await response.json();

                    if (response.ok && (data.success || data.status === 'success' || response.status === 200 || response.status === 202)) {
                        alert('Publishing retry queued successfully!');
                        window.location.reload();
                    } else {
                        const msg = data.message || data.error || 'Failed to retry publishing attempt.';
                        alert('Error: ' + msg);
                        btn.disabled = false;
                        btn.textContent = 'Retry Publish';
                    }
                } catch (err) {
                    alert('Network error: ' + err.message);
                    btn.disabled = false;
                    btn.textContent = 'Retry Publish';
                }
            }
        </script>
    @endif
</x-app-layout>
