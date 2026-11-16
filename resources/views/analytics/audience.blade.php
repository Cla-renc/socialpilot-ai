<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Analytics Studio · Audience</p>
                <h1>Audience Insights</h1>
                <p class="muted">Demographics, geography, and active hours across your social audience.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.dashboard') }}" class="button-secondary">&larr; Back to Overview</a>
                <a href="{{ route('analytics.reports') }}" class="button">Export Insights</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.content') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.platforms') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Platforms</a>
        <a href="{{ route('analytics.recommendations') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    {{-- Brand Filter Dropdown --}}
    <div class="card panel" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <form method="GET" action="{{ route('analytics.audience') }}" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <label for="brand_id" style="font-weight:600;font-size:0.88rem;">Filter by Brand:</label>
            <select name="brand_id" id="brand_id" onchange="this.form.submit()" style="padding:0.4rem 0.8rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.88rem;min-width:200px;">
                <option value="">All Brands</option>
                @foreach($brands as $b)
                    <option value="{{ $b->id }}" {{ $selectedBrand && $selectedBrand->id === $b->id ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
            @if($selectedBrand)
                <a href="{{ route('analytics.audience') }}" class="muted" style="font-size:0.82rem;">Clear filter</a>
            @endif
        </form>
    </div>

    {{-- Audience insight types grouped --}}
    @if($insights->isEmpty())
        <div class="card panel" style="text-align:center;padding:2.5rem;">
            <p class="muted">No audience insights recorded yet. Trigger collection or sync accounts to gather audience demographics.</p>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            @foreach($insights as $type => $group)
                <div class="card panel">
                    <h2 style="margin-top:0;margin-bottom:1rem;font-size:1.15rem;text-transform:capitalize;">
                        {{ str_replace('_', ' ', $type) }}
                    </h2>

                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                    <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Platform</th>
                                    <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Segment / Key</th>
                                    <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Data / Distribution</th>
                                    <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Collected At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group as $item)
                                    <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                        <td style="padding:0.6rem 0.75rem;text-transform:capitalize;">
                                            {{ $item->platform ?? 'All' }}
                                        </td>
                                        <td style="padding:0.6rem 0.75rem;font-weight:500;">
                                            {{ $item->segment_name ?? ($item->key ?? 'General') }}
                                        </td>
                                        <td style="padding:0.6rem 0.75rem;">
                                            @if(is_array($item->insight_data) || is_object($item->insight_data))
                                                <pre style="margin:0;font-size:0.8rem;background:rgba(0,0,0,0.03);padding:0.35rem 0.6rem;border-radius:4px;max-width:400px;overflow-x:auto;">{{ json_encode($item->insight_data, JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                <span>{{ $item->insight_data ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td style="padding:0.6rem 0.75rem;" class="muted">
                                            {{ $item->collected_at ? \Carbon\Carbon::parse($item->collected_at)->diffForHumans() : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
