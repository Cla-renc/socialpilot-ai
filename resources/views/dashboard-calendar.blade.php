<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing</p>
                <h1>Upcoming calendar</h1>
                <p class="muted">{{ $selectedBrand?->name ?? 'All brands' }} · next 90 days</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('dashboard', request()->only('brand_id')) }}" class="button-secondary">Dashboard</a>
                <a href="{{ route('brands.index') }}" class="button">Manage brands</a>
            </div>
        </div>
    </x-slot>

    <section class="card panel" style="margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('dashboard.calendar') }}" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;">
            <label style="min-width:min(100%, 280px);">
                <span class="label">Calendar brand</span>
                <select name="brand_id" aria-label="Filter calendar by brand">
                    <option value="">All brands</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand['id'] }}" @selected($selectedBrand?->id === $brand['id'])>{{ $brand['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="button">Apply filter</button>
            <button type="submit" class="button-secondary" formnovalidate>Refresh</button>
        </form>
    </section>

    <section class="card panel">
        <h2>Scheduled content</h2>
        @forelse ($calendar as $item)
            <div class="line-item">
                <div>
                    <strong>{{ $item['title'] }}</strong>
                    <div class="muted">{{ $item['brand_name'] }} · {{ ucfirst($item['platform']) }}</div>
                    <div class="muted">{{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->format('l, F j, Y · g:i A') }}</div>
                </div>
                <span class="badge badge--pending">{{ \Illuminate\Support\Str::headline($item['status']) }}</span>
            </div>
        @empty
            <p class="muted">No scheduled posts were found in the next 90 days.</p>
        @endforelse
    </section>
</x-app-layout>
