<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ $brand->name }} · {{ \Illuminate\Support\Str::headline($topic->category) }}</p>
                <h1>{{ $topic->title }}</h1>
                <p class="muted">Researched {{ $topic->researched_at->format('M j, Y g:i A') }}</p>
            </div>
            <div class="page-tools">
                <a class="button-secondary" href="{{ route('brands.trends.index', $brand) }}">Back to trends</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    <section class="dashboard-grid" aria-label="Trend scores">
        <article class="card stat-card"><span class="label">Brand relevance</span><div class="value">{{ $topic->relevance_score }}/100</div></article>
        <article class="card stat-card"><span class="label">Opportunity score</span><div class="value">{{ $topic->opportunity_score }}/100</div></article>
        <article class="card stat-card"><span class="label">Momentum</span><div class="value">{{ $topic->momentum ? \Illuminate\Support\Str::headline($topic->momentum) : 'Unavailable' }}</div></article>
        <article class="card stat-card"><span class="label">Source evidence</span><div class="value">{{ $topic->sources->count() }}</div></article>
    </section>

    <section class="content-grid" style="margin-top:1rem;">
        <article class="card panel">
            <h2>Research summary</h2>
            <p>{{ $topic->summary ?: $topic->description ?: 'No summary was produced from the available sources.' }}</p>
            <h3>Why it may matter to {{ $brand->name }}</h3>
            <p>{{ $topic->relevance_reason }}</p>
            <p class="muted">Relevance uses keyword overlap and brand-context matches; it is a deterministic research aid, not a guarantee of audience response.</p>
            @if ($topic->momentum_evidence)
                <h3>Momentum evidence</h3>
                <p>{{ $topic->momentum_evidence }}</p>
            @else
                <p class="muted">Momentum classification is unavailable because comparable historical measurements were not found.</p>
            @endif
        </article>
        <article class="card panel">
            <h2>Suggested content angles</h2>
            <p class="muted">These are research recommendations only. No post or draft has been generated.</p>
            @forelse ($topic->content_angles ?? [] as $angle)
                <div class="line-item">{{ $angle }}</div>
            @empty
                <p class="muted">No content angles were returned from the available evidence.</p>
            @endforelse
            @if ($topic->content_angle_error)
                <p class="muted">{{ $topic->content_angle_error }}</p>
            @elseif ($topic->content_angle_status === 'configuration_required')
                <p class="muted">Configure the optional Ollama angle generator to request content ideas.</p>
            @elseif ($topic->content_angle_status === 'disabled')
                <p class="muted">Optional Ollama angle generation is disabled.</p>
            @endif
            <h2 style="margin-top:1.5rem;">Feedback</h2>
            @foreach (['relevant' => 'Relevant', 'irrelevant' => 'Not relevant', 'saved' => 'Save', 'dismissed' => 'Dismiss'] as $value => $label)
                <form method="POST" action="{{ route('brands.trends.feedback', [$brand, $topic]) }}" style="display:inline-block;margin:.25rem 0;">
                    @csrf
                    <input type="hidden" name="feedback" value="{{ $value }}">
                    <button class="button-secondary" type="submit">{{ $label }}</button>
                </form>
            @endforeach
        </article>
    </section>

    <section class="card panel" style="margin-top:1rem;">
        <h2>Supporting sources</h2>
        @forelse ($topic->sources as $source)
            <article class="line-item">
                <div>
                    <strong>{{ $source->source_name }}</strong>
                    <div><a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->title }}</a></div>
                    @if ($source->summary)
                        <p class="muted">{{ $source->summary }}</p>
                    @endif
                    @if ($source->evidence)
                        <p class="muted">Evidence: {{ $source->evidence }}</p>
                    @endif
                    @if ($source->published_at)
                        <span class="muted">Published {{ $source->published_at->format('M j, Y g:i A') }}</span>
                    @else
                        <span class="muted">Publication date unavailable</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="muted">No source records are available for this topic.</p>
        @endforelse
    </section>
</x-app-layout>
