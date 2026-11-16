<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Brand management</p>
                <h1>My Brands</h1>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.create') }}" class="button">Create brand</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif
    @if (session('warning') || session('error'))
        <div class="card panel" role="alert" style="margin-bottom:1rem;">{{ session('warning') ?? session('error') }}</div>
    @endif

    <section class="card panel" style="margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('brands.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;">
            <input name="search" value="{{ request('search') }}" aria-label="Search brands" placeholder="Search name, industry, description">
            <select name="status" aria-label="Filter by status">
                <option value="">All statuses</option>
                @foreach (['draft', 'active', 'paused'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="industry" aria-label="Filter by industry">
                <option value="">All industries</option>
                @foreach ($industries as $industry)
                    <option value="{{ $industry }}" @selected(request('industry') === $industry)>{{ $industry }}</option>
                @endforeach
            </select>
            <select name="sort" aria-label="Sort brands">
                @foreach (['created_at' => 'Date created', 'name' => 'Name', 'industry' => 'Industry', 'status' => 'Status'] as $column => $label)
                    <option value="{{ $column }}" @selected(request('sort', 'created_at') === $column)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="direction" aria-label="Sort direction">
                <option value="desc" @selected(request('direction', 'desc') === 'desc')>Descending</option>
                <option value="asc" @selected(request('direction') === 'asc')>Ascending</option>
            </select>
            <button type="submit" class="button-secondary">Filter</button>
            @if (request()->hasAny(['search', 'status', 'industry', 'sort', 'direction']))
                <a href="{{ route('brands.index') }}" class="button-secondary">Clear</a>
            @endif
        </form>
    </section>

    <section class="content-grid">
        @forelse ($brands as $brand)
            <article class="card panel">
                <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;">
                    <div>
                        @if ($brand->logo_url)
                            <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }} logo" width="56" height="56" style="object-fit:cover;border-radius:12px;margin-bottom:8px;">
                        @endif
                        <h2>{{ $brand->name }}</h2>
                        <div class="muted">{{ $brand->industry ?? $brand->category ?? 'Brand profile' }}</div>
                        <div class="muted">Niche: {{ $brand->niche ?: 'Not set' }}</div>
                    </div>
                    <span class="badge badge--{{ $brand->status === 'active' ? 'active' : 'pending' }}">{{ ucfirst($brand->status) }}</span>
                </div>

                <p class="muted" style="margin-top:1rem;">{{ $brand->description ?: 'No description yet.' }}</p>

                <div class="list" style="margin-top:1.25rem;">
                    <div class="line-item">
                        <span class="muted">Audience</span>
                        <strong>{{ $brand->audience ?: 'Not set' }}</strong>
                    </div>
                    <div class="line-item">
                        <span class="muted">Region</span>
                        <strong>{{ $brand->region ?: 'Not set' }}</strong>
                    </div>
                    <div class="line-item">
                        <span class="muted">Tone</span>
                        <strong>{{ $brand->tone ?: 'Not set' }}</strong>
                    </div>
                    <div class="line-item">
                        <span class="muted">Connected accounts</span>
                        <strong>{{ $brand->connected_accounts_count }}</strong>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:1.2rem;flex-wrap:wrap;">
                    <a href="{{ route('brands.show', $brand) }}" class="button-secondary">View</a>
                    <a href="{{ route('brands.edit', $brand) }}" class="button-secondary">Edit</a>
                    <a href="{{ route('brands.settings', $brand) }}" class="button-secondary">Settings</a>
                    <a href="{{ route('brands.social-accounts.index', $brand) }}" class="button-secondary">Social accounts</a>
                    <form method="POST" action="{{ route('brands.destroy', $brand) }}" onsubmit="return confirm('Delete this brand? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button-secondary" style="border-color: rgba(248,113,113,0.4); color:#fca5a5;">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <article class="card panel" style="grid-column:1 / -1;">
                <h2>No brands yet</h2>
                <p class="muted">Create your first brand profile to configure tone, audience, social accounts and AI publishing settings.</p>
                <a href="{{ route('brands.create') }}" class="button" style="margin-top:1rem;display:inline-flex;">Create first brand</a>
            </article>
        @endforelse
    </section>
    <div style="margin-top:1rem;">{{ $brands->links() }}</div>
</x-app-layout>
