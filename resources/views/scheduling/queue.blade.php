<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing Queues · Phase 8</p>
                <h1>Publishing Queue for {{ $brand->name }}</h1>
                <p class="muted">Automated content staging and queue prioritization for regular publishing cadence.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.scheduling.schedule', $brand) }}" class="button">+ Schedule Post</a>
                <a href="{{ route('scheduling.dashboard') }}" class="button-secondary">Overview</a>
            </div>
        </div>
    </x-slot>

    <div id="alertBox" style="display:none;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:0.9rem;"></div>

    @if($queues->isEmpty())
        <div class="card panel" style="text-align:center;padding:3rem 1rem;">
            <h3 style="margin-top:0;">No Publishing Queues Configured</h3>
            <p class="muted" style="max-width:500px;margin:0.5rem auto 1.5rem;">
                A publishing queue automatically schedules your staged content according to optimal intervals and posting days.
            </p>
            <button type="button" class="button" onclick="createDefaultQueue()">Initialize Default Queue</button>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            @foreach($queues as $queue)
                <div class="card panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--stroke, #e5e7eb);padding-bottom:1rem;margin-bottom:1rem;flex-wrap:wrap;gap:0.75rem;">
                        <div>
                            <div style="display:flex;align-items:center;gap:0.75rem;">
                                <h2 style="margin:0;font-size:1.2rem;">{{ $queue->name }}</h2>
                                <span class="badge" style="padding:0.2rem 0.6rem;border-radius:12px;font-size:0.75rem;font-weight:600;background:{{ $queue->status === 'active' ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)' }};color:{{ $queue->status === 'active' ? '#059669' : '#dc2626' }};">
                                    {{ ucfirst($queue->status) }}
                                </span>
                            </div>
                            <div class="muted" style="font-size:0.82rem;margin-top:0.25rem;">
                                Interval: {{ $queue->posting_interval_minutes ?? 120 }} mins · Timezone: {{ $queue->timezone ?? 'UTC' }}
                            </div>
                        </div>

                        <div style="display:flex;gap:0.5rem;">
                            @if($queue->status === 'active')
                                <button type="button" class="button-secondary" onclick="toggleQueueStatus({{ $queue->id }}, 'pause')">Pause Queue</button>
                            @else
                                <button type="button" class="button" onclick="toggleQueueStatus({{ $queue->id }}, 'resume')">Resume Queue</button>
                            @endif
                        </div>
                    </div>

                    {{-- Items inside queue --}}
                    <div>
                        <h4 style="margin:0 0 0.75rem;font-size:0.95rem;">Queue Items ({{ $queue->items->count() }})</h4>

                        @if($queue->items->isEmpty())
                            <p class="muted" style="font-size:0.88rem;padding:1rem 0;text-align:center;">Queue is empty. New content drafts ready for scheduling will appear here.</p>
                        @else
                            <div style="display:flex;flex-direction:column;gap:0.5rem;" id="queueItemsList_{{ $queue->id }}">
                                @foreach($queue->items->sortBy('position') as $item)
                                    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.6rem 0.85rem;border:1px solid var(--stroke, #e5e7eb);border-radius:6px;background:var(--surface, #fff);">
                                        <div style="display:flex;align-items:center;gap:0.75rem;">
                                            <span style="font-weight:700;color:var(--muted);width:24px;">#{{ $item->position }}</span>
                                            <div>
                                                <div style="font-weight:600;font-size:0.9rem;">
                                                    {{ $item->scheduledPost?->title ?? ($item->contentDraft?->title ?? 'Queue Post #'.$item->id) }}
                                                </div>
                                                <div class="muted" style="font-size:0.78rem;">
                                                    Status: {{ ucfirst($item->status) }}
                                                    @if($item->scheduledPost?->scheduled_at)
                                                        · Scheduled for {{ \Carbon\Carbon::parse($item->scheduledPost->scheduled_at)->format('M j, g:i A') }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display:flex;align-items:center;gap:0.5rem;">
                                            <button type="button" class="button-secondary" style="padding:0.2rem 0.5rem;font-size:0.75rem;" onclick="removeQueueItem({{ $item->id }})">Remove</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <script>
        const brandId = {{ $brand->id }};

        function showMessage(msg, isError = false) {
            const box = document.getElementById('alertBox');
            box.style.display = 'block';
            box.style.background = isError ? 'rgba(239, 68, 68, 0.12)' : 'rgba(16, 185, 129, 0.12)';
            box.style.color = isError ? '#991b1b' : '#065f46';
            box.textContent = msg;
        }

        async function createDefaultQueue() {
            try {
                const res = await fetch(`/api/v1/brands/${brandId}/publishing-queue`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        name: 'Primary Publishing Queue',
                        posting_interval_minutes: 180,
                        status: 'active'
                    })
                });

                if (res.ok) {
                    location.reload();
                } else {
                    const d = await res.json();
                    showMessage(d.message || 'Error initializing queue', true);
                }
            } catch (err) {
                showMessage(err.message, true);
            }
        }

        async function toggleQueueStatus(queueId, action) {
            try {
                const res = await fetch(`/api/v1/publishing-queue/${queueId}/${action}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (res.ok) {
                    location.reload();
                } else {
                    const d = await res.json();
                    showMessage(d.message || `Could not ${action} queue`, true);
                }
            } catch (err) {
                showMessage(err.message, true);
            }
        }

        async function removeQueueItem(itemId) {
            if (!confirm('Remove this post from the publishing queue?')) return;

            try {
                const res = await fetch(`/api/v1/publishing-queue/items/${itemId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (res.ok) {
                    location.reload();
                } else {
                    const d = await res.json();
                    showMessage(d.message || 'Could not remove item', true);
                }
            } catch (err) {
                showMessage(err.message, true);
            }
        }
    </script>
</x-app-layout>
