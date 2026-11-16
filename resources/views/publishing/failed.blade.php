<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing Studio · Phase 9</p>
                <h1>Failed Publishing Attempts</h1>
                <p class="muted">Review failed dispatch attempts, inspect error diagnostics, and trigger retries.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('publishing.history') }}" class="button-secondary">All History</a>
                <a href="{{ route('publishing.dashboard') }}" class="button-secondary">Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('publishing.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('publishing.publish-now') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Publish Now</a>
        <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">History</a>
        <a href="{{ route('publishing.failed') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Failed Attempts</a>
        <a href="{{ route('publishing.notifications') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Notifications</a>
    </div>

    {{-- Alert Notification Banner --}}
    <div id="statusAlert" style="display:none;margin-bottom:1.25rem;padding:0.85rem 1.25rem;border-radius:8px;font-size:0.9rem;"></div>

    <div class="card panel" style="padding:1.5rem;">
        @if($attempts->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;" class="muted">
                <div style="font-size:2.5rem;margin-bottom:0.75rem;color:#34d399;">&check;</div>
                <h3 style="margin:0 0 0.5rem;color:var(--text);font-size:1.2rem;">No Failed Attempts</h3>
                <p style="font-size:0.95rem;max-width:450px;margin:0 auto 1.5rem;">Great news! None of your publishing attempts have failed, or all issues have been resolved.</p>
                <a href="{{ route('publishing.dashboard') }}" class="button">Go to Dashboard</a>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--stroke);color:var(--muted);text-transform:uppercase;font-size:0.75rem;letter-spacing:0.05em;">
                            <th style="padding:0.75rem 1rem;">Platform</th>
                            <th style="padding:0.75rem 1rem;">Error Code</th>
                            <th style="padding:0.75rem 1rem;">Error Message</th>
                            <th style="padding:0.75rem 1rem;">Attempt #</th>
                            <th style="padding:0.75rem 1rem;">Is Retryable</th>
                            <th style="padding:0.75rem 1rem;">Created At</th>
                            <th style="padding:0.75rem 1rem;text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attempts as $attempt)
                            <tr style="border-bottom:1px solid var(--stroke);" id="attempt-row-{{ $attempt->id }}">
                                <td style="padding:0.75rem 1rem;">
                                    <span style="display:inline-block;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.8rem;font-weight:600;background:rgba(255,255,255,0.06);text-transform:capitalize;">
                                        {{ $attempt->platform }}
                                    </span>
                                </td>
                                <td style="padding:0.75rem 1rem;font-family:monospace;font-size:0.82rem;color:var(--warning, #fbbf24);">
                                    {{ $attempt->error_code ?: 'UNKNOWN' }}
                                </td>
                                <td style="padding:0.75rem 1rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--danger, #f87171);">
                                    {{ $attempt->error_message ?: '—' }}
                                </td>
                                <td style="padding:0.75rem 1rem;color:var(--muted);">
                                    #{{ $attempt->attempt_number ?? 1 }}
                                </td>
                                <td style="padding:0.75rem 1rem;">
                                    @if($attempt->is_retryable)
                                        <span style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:rgba(52, 211, 153, 0.15);color:#34d399;">
                                            Retryable
                                        </span>
                                    @else
                                        <span style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:rgba(148, 163, 184, 0.15);color:#94a3b8;">
                                            No
                                        </span>
                                    @endif
                                </td>
                                <td style="padding:0.75rem 1rem;white-space:nowrap;color:var(--muted);font-size:0.82rem;">
                                    {{ $attempt->created_at ? $attempt->created_at->format('M j, Y g:i A') : '—' }}
                                </td>
                                <td style="padding:0.75rem 1rem;text-align:right;white-space:nowrap;">
                                    <div style="display:inline-flex;gap:0.5rem;align-items:center;">
                                        @if($attempt->is_retryable)
                                            <button type="button" 
                                                    onclick="retryAttempt({{ $attempt->id }}, this)" 
                                                    class="button" 
                                                    style="padding:0.3rem 0.75rem;font-size:0.8rem;background:var(--primary, #8b5cf6);">
                                                Retry
                                            </button>
                                        @endif
                                        <a href="{{ route('publishing.show', $attempt) }}" class="button-secondary" style="padding:0.3rem 0.65rem;font-size:0.8rem;">
                                            Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:1.5rem;">
                {{ $attempts->links() }}
            </div>
        @endif
    </div>

    <script>
        function showAlert(message, isSuccess = true) {
            const el = document.getElementById('statusAlert');
            el.style.display = 'block';
            el.style.background = isSuccess ? 'rgba(52, 211, 153, 0.15)' : 'rgba(248, 113, 113, 0.15)';
            el.style.border = isSuccess ? '1px solid #34d399' : '1px solid #f87171';
            el.style.color = isSuccess ? '#34d399' : '#f87171';
            el.textContent = message;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function retryAttempt(attemptId, btn) {
            if (!confirm('Are you sure you want to retry this publishing attempt?')) {
                return;
            }

            const originalText = btn.textContent;
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
                    showAlert(`Attempt #${attemptId} retry queued successfully!`, true);
                    btn.textContent = 'Queued';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    const msg = data.message || data.error || 'Failed to retry publishing attempt.';
                    showAlert(`Error: ${msg}`, false);
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (err) {
                showAlert(`Network error: ${err.message}`, false);
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
    </script>
</x-app-layout>
