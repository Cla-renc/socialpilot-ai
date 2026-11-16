<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing Studio · Phase 9</p>
                <h1>Publishing Notifications</h1>
                <p class="muted">Live operational alerts, failure notifications, and delivery confirmations.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('publishing.history') }}" class="button-secondary">History</a>
                <a href="{{ route('publishing.dashboard') }}" class="button-secondary">Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('publishing.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('publishing.publish-now') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Publish Now</a>
        <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">History</a>
        <a href="{{ route('publishing.failed') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Failed Attempts</a>
        <a href="{{ route('publishing.notifications') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Notifications</a>
    </div>

    <div class="card panel" style="padding:1.5rem;">
        @if($notifications->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;" class="muted">
                <div style="font-size:2rem;margin-bottom:0.5rem;">🔔</div>
                <h3 style="margin:0 0 0.5rem;color:var(--text);font-size:1.15rem;">No Publishing Notifications</h3>
                <p style="font-size:0.92rem;max-width:450px;margin:0 auto;">You have no publishing alerts or failure reports at this time.</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:1rem;">
                @foreach($notifications as $notif)
                    @php
                        $type = strtolower($notif->notification_type ?? ($notif->type ?? 'info'));
                        $typeStyle = match(true) {
                            str_contains($type, 'success') || str_contains($type, 'published') => ['bg' => 'rgba(52, 211, 153, 0.15)', 'color' => '#34d399', 'label' => 'Success'],
                            str_contains($type, 'fail') || str_contains($type, 'error') => ['bg' => 'rgba(248, 113, 113, 0.15)', 'color' => '#f87171', 'label' => 'Failure'],
                            str_contains($type, 'part') => ['bg' => 'rgba(251, 146, 60, 0.15)', 'color' => '#fb923c', 'label' => 'Partial'],
                            default => ['bg' => 'rgba(139, 92, 246, 0.15)', 'color' => '#a78bfa', 'label' => ucfirst($type)],
                        };
                        $isUnread = is_null($notif->read_at);
                    @endphp
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1.15rem;border-radius:10px;background:var(--surface);border:1px solid {{ $isUnread ? 'rgba(139, 92, 246, 0.4)' : 'var(--stroke)' }};gap:1rem;position:relative;">
                        <div style="display:flex;gap:1rem;align-items:flex-start;flex:1;">
                            {{-- Unread Dot --}}
                            @if($isUnread)
                                <div title="Unread notification" style="width:10px;height:10px;border-radius:50%;background:#8b5cf6;margin-top:6px;flex-shrink:0;box-shadow:0 0 8px #8b5cf6;"></div>
                            @else
                                <div style="width:10px;height:10px;border-radius:50%;background:rgba(148, 163, 184, 0.25);margin-top:6px;flex-shrink:0;"></div>
                            @endif

                            <div style="flex:1;">
                                <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.35rem;">
                                    <span style="display:inline-block;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.75rem;font-weight:700;background:{{ $typeStyle['bg'] }};color:{{ $typeStyle['color'] }};">
                                        {{ $typeStyle['label'] }}
                                    </span>
                                    @if($notif->platform)
                                        <span style="font-size:0.75rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:4px;background:rgba(255,255,255,0.06);text-transform:capitalize;">
                                            {{ $notif->platform }}
                                        </span>
                                    @endif
                                    <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--text);">
                                        {{ $notif->title }}
                                    </h3>
                                </div>

                                <p style="margin:0.25rem 0 0.5rem;font-size:0.9rem;color:var(--text);line-height:1.5;">
                                    {{ $notif->message ?? $notif->body }}
                                </p>

                                <div class="muted" style="font-size:0.8rem;display:flex;gap:1rem;flex-wrap:wrap;">
                                    <span>{{ $notif->created_at ? $notif->created_at->format('M j, Y g:i A') : '—' }}</span>
                                    @if($notif->publishing_attempt_id)
                                        <a href="{{ route('publishing.show', $notif->publishing_attempt_id) }}" style="color:var(--primary-2, #22d3ee);text-decoration:underline;">
                                            Inspect Attempt #{{ $notif->publishing_attempt_id }} &rarr;
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:1.5rem;">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
