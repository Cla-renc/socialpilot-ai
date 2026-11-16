<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Audit Trail · Phase 8</p>
                <h1>Autopilot Activity Log for {{ $brand->name }}</h1>
                <p class="muted">Complete chronological log of autonomous agent executions, scheduling events, and manual overrides.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.scheduling.autopilot', $brand) }}" class="button-secondary">Autopilot Studio</a>
                <a href="{{ route('scheduling.calendar') }}" class="button-secondary">Calendar</a>
            </div>
        </div>
    </x-slot>

    <div class="card panel">
        <h3 style="margin-top:0;font-size:1.1rem;margin-bottom:1rem;">Event History</h3>

        @if($logs->isEmpty())
            <div style="padding:2.5rem 1rem;text-align:center;" class="muted">
                <p>No activity records logged for this brand yet.</p>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                @foreach($logs as $log)
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:0.85rem;border:1px solid var(--stroke, #e5e7eb);border-radius:6px;gap:1rem;">
                        <div style="display:flex;gap:0.75rem;align-items:flex-start;">
                            @php
                                $severity = $log->status ?? ($log->severity ?? 'info');
                                $color = match($severity) {
                                    'success' => '#059669',
                                    'warning' => '#d97706',
                                    'error' => '#dc2626',
                                    default => '#4f46e5'
                                };
                                $bg = match($severity) {
                                    'success' => 'rgba(16,185,129,0.12)',
                                    'warning' => 'rgba(245,158,11,0.12)',
                                    'error' => 'rgba(239,68,68,0.12)',
                                    default => 'rgba(79,70,229,0.12)'
                                };
                            @endphp
                            <div style="width:10px;height:10px;border-radius:50%;background:{{ $color }};margin-top:6px;flex-shrink:0;"></div>
                            <div>
                                <div style="font-weight:600;font-size:0.92rem;">
                                    {{ ucwords(str_replace('_', ' ', $log->action ?? ($log->event_type ?? 'Event'))) }}
                                </div>
                                @if(!empty($log->details))
                                    <div class="muted" style="font-size:0.82rem;margin-top:0.25rem;">
                                        @if(is_array($log->details))
                                            @foreach($log->details as $k => $v)
                                                <span><strong>{{ $k }}:</strong> {{ is_array($v) ? json_encode($v) : $v }}</span>{{ !$loop->last ? ' · ' : '' }}
                                            @endforeach
                                        @else
                                            {{ $log->details }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div style="text-align:right;flex-shrink:0;">
                            <span class="badge" style="font-size:0.72rem;padding:0.2rem 0.5rem;border-radius:8px;background:{{ $bg }};color:{{ $color }};font-weight:600;">
                                {{ strtoupper($severity) }}
                            </span>
                            <div class="muted" style="font-size:0.75rem;margin-top:0.35rem;">
                                {{ $log->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:1.5rem;">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
