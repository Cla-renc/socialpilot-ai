<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ $brand->name }} · AI Content</p>
                <h1>Content library</h1>
                <p class="muted">All generated and saved content drafts for this brand.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.content.studio', $brand) }}" class="button">New content</a>
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Brand workspace</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    {{-- Filters --}}
    <section class="card panel" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('brands.content.index', $brand) }}" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;">
            @if ($brands->count() > 1)
            <label>
                <span class="label">Brand</span>
                <select aria-label="Switch brand" onchange="if(this.value) window.location.href=this.value">
                    @foreach ($brands as $b)
                        <option value="{{ route('brands.content.index', $b) }}" @selected($b->id === $brand->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </label>
            @endif

            <label>
                <span class="label">Search</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Title or body…">
            </label>

            <label>
                <span class="label">Platform</span>
                <select name="platform">
                    <option value="">All platforms</option>
                    @foreach (['linkedin','instagram','tiktok','youtube','facebook'] as $p)
                        <option value="{{ $p }}" @selected(request('platform') === $p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="label">Category</span>
                <select name="category">
                    <option value="">All categories</option>
                    @foreach (['educational','motivational','promotional','storytelling','thought_leadership','community','announcement','question'] as $c)
                        <option value="{{ $c }}" @selected(request('category') === $c)>{{ Str::headline($c) }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="label">Objective</span>
                <select name="objective">
                    <option value="">All objectives</option>
                    @foreach (['awareness','engagement','education','community','brand_authority','promotion','traffic','lead_generation'] as $o)
                        <option value="{{ $o }}" @selected(request('objective') === $o)>{{ Str::headline($o) }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="label">Status</span>
                <select name="status">
                    <option value="">All</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="published" @selected(request('status') === 'published')>Published</option>
                </select>
            </label>

            <div style="display:flex;gap:0.5rem;">
                <button type="submit" class="button">Filter</button>
                @if (request()->hasAny(['search','platform','category','objective','status']))
                    <a href="{{ route('brands.content.index', $brand) }}" class="button-secondary">Clear</a>
                @endif
            </div>
        </form>
    </section>

    {{-- Draft list --}}
    @if ($drafts->isEmpty())
        <div class="card panel" style="text-align:center;padding:3rem;">
            <p style="font-size:1.1rem;margin-bottom:0.5rem;">No content drafts yet.</p>
            <p class="muted">Use the Content Studio to generate your first piece of content.</p>
            <a href="{{ route('brands.content.studio', $brand) }}" class="button" style="margin-top:1rem;">Open Content Studio</a>
        </div>
    @else
        <section style="display:flex;flex-direction:column;gap:0.75rem;">
            @foreach ($drafts as $draft)
                <article class="card panel" style="display:flex;gap:1rem;align-items:flex-start;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.35rem;">
                            <strong style="font-size:1rem;">
                                <a href="{{ route('brands.content.show', [$brand, $draft]) }}" style="text-decoration:none;color:inherit;">
                                    {{ $draft->title ?: 'Untitled draft' }}
                                </a>
                            </strong>
                            <span class="badge">{{ ucfirst($draft->platform ?? 'General') }}</span>
                            @if ($draft->category)
                                <span class="badge badge--pending">{{ Str::headline($draft->category) }}</span>
                            @endif
                            @if ($draft->status === 'approved')
                                <span class="badge badge--success">Approved</span>
                            @elseif ($draft->status === 'draft')
                                <span class="badge">Draft</span>
                            @endif
                            @if ($draft->is_ai_generated)
                                <span class="badge badge--pending">AI</span>
                            @endif
                        </div>
                        @if ($draft->hook)
                            <p class="muted" style="font-size:0.9rem;margin-bottom:0.25rem;font-style:italic;">{{ Str::limit($draft->hook, 120) }}</p>
                        @elseif ($draft->body)
                            <p class="muted" style="font-size:0.9rem;margin-bottom:0.25rem;">{{ Str::limit($draft->body, 160) }}</p>
                        @endif
                        <div style="display:flex;gap:1rem;font-size:0.82rem;" class="muted">
                            @if ($draft->objective)
                                <span>{{ Str::headline($draft->objective) }}</span>
                            @endif
                            @if ($draft->quality_score !== null)
                                <span>Quality: {{ $draft->quality_score }}/100</span>
                            @endif
                            <span>{{ $draft->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div style="display:flex;gap:0.5rem;flex-shrink:0;">
                        <a href="{{ route('brands.content.show', [$brand, $draft]) }}" class="button-secondary">Edit</a>
                    </div>
                </article>
            @endforeach
        </section>

        <div style="margin-top:1.5rem;">
            {{ $drafts->links() }}
        </div>
    @endif
</x-app-layout>
