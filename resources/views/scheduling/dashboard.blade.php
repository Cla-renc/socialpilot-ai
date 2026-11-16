<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">AI Planning & Autopilot · Phase 8</p>
                <h1>Scheduling Studio</h1>
                <p class="muted">Plan, schedule, queue, and automate social media publishing across all your brands.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('scheduling.calendar') }}" class="button">Content Calendar</a>
                @if($brands->isNotEmpty())
                    <a href="{{ route('brands.scheduling.schedule', $brands->first()) }}" class="button-secondary">+ Schedule Post</a>
                @endif
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('scheduling.dashboard') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('scheduling.calendar') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Calendar</a>
        @if($brands->isNotEmpty())
            <a href="{{ route('brands.scheduling.best-times', $brands->first()) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Best Times</a>
            <a href="{{ route('brands.scheduling.queue', $brands->first()) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Publishing Queue</a>
            <a href="{{ route('brands.scheduling.autopilot', $brands->first()) }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Autopilot Agent</a>
        @endif
    </div>

    {{-- Stats Grid --}}
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;">
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Scheduled Posts</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;">{{ $stats['total_scheduled'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Pending Approval</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;color:var(--warning, #f59e0b);">{{ $stats['pending_approval'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Published This Week</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;">{{ $stats['published_this_week'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Active Queues</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;">{{ $stats['active_queues'] }}</div>
        </div>
        <div class="card panel">
            <span class="muted" style="font-size:0.85rem;">Autopilot Active</span>
            <div style="font-size:2rem;font-weight:700;margin-top:0.35rem;color:var(--success, #10b981);">{{ $stats['autopilot_enabled'] }}</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        {{-- Upcoming Posts Section --}}
        <div class="card panel">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <h2 style="margin:0;font-size:1.15rem;">Upcoming Scheduled Posts</h2>
                <a href="{{ route('scheduling.calendar') }}" style="font-size:0.85rem;">View Calendar &rarr;</a>
            </div>

            @if($upcoming->isEmpty())
                <p class="muted" style="padding:1.5rem 0;text-align:center;">No upcoming scheduled posts. <a href="{{ route('scheduling.calendar') }}">Open the calendar</a> or schedule a draft.</p>
            @else
                <div style="display:flex;flex-direction:column;gap:0.75rem;">
                    @foreach($upcoming as $post)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;border:1px solid var(--stroke, #e5e7eb);border-radius:8px;">
                            <div style="flex:1;">
                                <div style="font-weight:600;font-size:0.95rem;">{{ $post->title }}</div>
                                <div class="muted" style="font-size:0.82rem;margin-top:0.25rem;">
                                    <strong>{{ $post->brand->name ?? 'Brand' }}</strong> · 
                                    @foreach($post->platforms as $plat)
                                        <span style="text-transform:capitalize;">{{ $plat->platform }}</span>{{ !$loop->last ? ', ' : '' }}
                                    @endforeach
                                    · {{ \Carbon\Carbon::parse($post->scheduled_at)->setTimezone($post->timezone ?: 'UTC')->format('M j, Y g:i A') }}
                                </div>
                            </div>
                            <span class="badge" style="padding:0.25rem 0.6rem;border-radius:12px;font-size:0.78rem;background:rgba(59,130,246,0.12);color:#2563eb;">
                                {{ ucfirst($post->status) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Side Actions and Brands --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">Quick Actions</h3>
                <div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.75rem;">
                    <a href="{{ route('scheduling.calendar') }}" class="button" style="text-align:center;">Open Calendar</a>
                    @if($brands->isNotEmpty())
                        <a href="{{ route('brands.scheduling.schedule', $brands->first()) }}" class="button-secondary" style="text-align:center;">+ Schedule Post</a>
                        <a href="{{ route('brands.scheduling.best-times', $brands->first()) }}" class="button-secondary" style="text-align:center;">AI Best Times</a>
                        <a href="{{ route('brands.scheduling.autopilot', $brands->first()) }}" class="button-secondary" style="text-align:center;">Autopilot Settings</a>
                    @endif
                </div>
            </div>

            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">Brands</h3>
                @forelse($brands as $b)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--stroke, #e5e7eb);">
                        <span style="font-weight:500;">{{ $b->name }}</span>
                        <div style="display:flex;gap:0.35rem;">
                            <a href="{{ route('brands.scheduling.schedule', $b) }}" class="button-secondary" style="font-size:0.78rem;padding:0.25rem 0.5rem;">Schedule</a>
                            <a href="{{ route('brands.scheduling.autopilot', $b) }}" class="button-secondary" style="font-size:0.78rem;padding:0.25rem 0.5rem;">Autopilot</a>
                        </div>
                    </div>
                @empty
                    <p class="muted">No brands found.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
