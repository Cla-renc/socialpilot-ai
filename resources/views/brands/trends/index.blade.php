<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Brand workspace</p>
                <h1>Trend intelligence</h1>
                <p class="muted">{{ $brand->name }} · Evidence-led research</p>
            </div>
            <div class="page-tools">
                <a class="button-secondary" href="{{ route('brands.show', $brand) }}">Brand workspace</a>
                <a class="button-secondary" href="{{ route('brands.trends.index', ['brand' => $brand] + request()->query()) }}">Refresh</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    <section class="card panel" style="margin-bottom:1rem;">
        <form method="GET" action="{{ route('brands.trends.index', $brand) }}" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;">
            <label>
                <span class="label">Brand</span>
                <select aria-label="Select brand" onchange="if(this.value) window.location.href=this.value">
                    @foreach ($brands as $option)
                        <option value="{{ route('brands.trends.index', $option) }}" @selected($option->id === $brand->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Category</span>
                <select name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ \Illuminate\Support\Str::headline($category) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Momentum</span>
                <select name="momentum">
                    <option value="">All classifications</option>
                    @foreach (['emerging', 'growing', 'established', 'declining', 'time_sensitive', 'evergreen'] as $momentum)
                        <option value="{{ $momentum }}" @selected(request('momentum') === $momentum)>{{ \Illuminate\Support\Str::headline($momentum) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Minimum relevance</span>
                <input type="number" min="0" max="100" name="min_relevance" value="{{ request('min_relevance') }}">
            </label>
            <label>
                <span class="label">Researched from</span>
                <input type="date" name="from" value="{{ request('from') }}">
            </label>
            <label>
                <span class="label">Researched to</span>
                <input type="date" name="to" value="{{ request('to') }}">
            </label>
            <label>
                <span class="label">Source</span>
                <select name="source">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source['key'] }}" @selected(request('source') === $source['key'])>{{ $source['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Geographic market</span>
                <input type="text" name="geographic_market" maxlength="128" value="{{ request('geographic_market') }}" placeholder="Filter by market">
            </label>
            <label>
                <span class="label">Sort</span>
                <select name="sort">
                    <option value="recent" @selected(request('sort', 'recent') === 'recent')>Most recent</option>
                    <option value="relevance" @selected(request('sort') === 'relevance')>Relevance</option>
                    <option value="opportunity" @selected(request('sort') === 'opportunity')>Opportunity</option>
                </select>
            </label>
            <label style="display:flex;align-items:center;gap:.45rem;">
                <input type="checkbox" name="saved" value="1" @checked(request()->boolean('saved'))>
                Saved only
            </label>
            <button type="submit" class="button">Filter</button>
        </form>
    </section>

    <section class="dashboard-grid" aria-label="Trend research overview">
        @foreach ([
            'Discovered topics' => $metrics['topics'],
            'High relevance (75+)' => $metrics['high_relevance'],
            'Emerging / growing' => $metrics['emerging'],
            'Research running' => $metrics['running'],
        ] as $label => $value)
            <article class="card stat-card">
                <span class="label">{{ $label }}</span>
                <div class="value">{{ number_format($value) }}</div>
            </article>
        @endforeach
    </section>

    <section class="content-grid" style="margin-top:1rem;">
        <article class="card panel">
            <h2>Research controls</h2>
            <p class="muted">Research uses configured, permitted sources. Topics are not labelled as trends without source evidence.</p>
            <form method="POST" action="{{ route('brands.trends.research', $brand) }}" class="trend-form">
                @csrf
                <label>
                    <span class="label">Research period</span>
                    <select name="days">
                        <option value="7">Past 7 days</option>
                        <option value="30" selected>Past 30 days</option>
                        <option value="90">Past 90 days</option>
                    </select>
                </label>
                <label>
                    <span class="label">Geographic market</span>
                    <input type="text" name="geographic_market" maxlength="128" value="{{ old('geographic_market', $brand->region) }}" placeholder="Use brand's configured market">
                </label>
                <div>
                    <span class="label">Research categories</span>
                    @foreach ($categoryOptions as $key => $label)
                        <label class="trend-source-choice">
                            <input type="checkbox" name="categories[]" value="{{ $key }}">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div>
                    <span class="label">Available sources</span>
                    @foreach ($sources as $source)
                        <label class="trend-source-choice">
                            <input type="checkbox" name="sources[]" value="{{ $source['key'] }}" @checked($source['connection_status'] === 'ready') @disabled($source['connection_status'] !== 'ready')>
                            <span>{{ $source['name'] }} · {{ \Illuminate\Support\Str::headline($source['connection_status']) }}</span>
                        </label>
                    @endforeach
                </div>
                <button class="button" type="submit">Start research</button>
            </form>
        </article>

        <article class="card panel">
            <h2>Source status</h2>
            @foreach ($sources as $source)
                <div class="line-item">
                    <div>
                        <strong>{{ $source['name'] }}</strong>
                        <div class="muted">
                            @if ($source['last_successful_research_at'])
                                Last successful research {{ \Illuminate\Support\Carbon::parse($source['last_successful_research_at'])->diffForHumans() }}
                            @elseif ($source['connection_status'] === 'not_implemented')
                                This connector is not implemented.
                            @else
                                No successful source request recorded.
                            @endif
                        </div>
                        @if ($source['last_error'])
                            <div class="muted">{{ $source['last_error'] }}</div>
                        @endif
                    </div>
                    <span class="badge {{ $source['connection_status'] === 'ready' ? 'badge--active' : 'badge--pending' }}">{{ \Illuminate\Support\Str::headline($source['connection_status']) }}</span>
                </div>
            @endforeach
        </article>
    </section>

    <section class="card panel" style="margin-top:1rem;">
        <div class="trend-section-heading">
            <div>
                <h2>Recent research</h2>
                <p class="muted">
                    @if ($lastResearch)
                        Last research {{ $lastResearch->created_at->diffForHumans() }} · {{ \Illuminate\Support\Str::headline($lastResearch->status) }}
                    @else
                        No research has been run for this brand.
                    @endif
                </p>
            </div>
        </div>
        @forelse ($jobs as $job)
            <div class="line-item">
                <div>
                    <strong>Research #{{ $job->id }}</strong>
                    <div class="muted">{{ $job->created_at->format('M j, Y g:i A') }} · {{ count($job->source_status ?? []) }} source statuses</div>
                    @if ($job->error_message)
                        <div class="muted">{{ $job->error_message }}</div>
                    @endif
                </div>
                <span class="badge badge--pending">{{ \Illuminate\Support\Str::headline($job->status) }}</span>
            </div>
        @empty
            <p class="muted">Research jobs will appear here after you start a run.</p>
        @endforelse
    </section>

    <section class="card panel" style="margin-top:1rem;">
        <h2>Automatic research schedule</h2>
        <p class="muted">Recurring research is off unless you select a frequency. Schedules use the time zone you choose.</p>
        <form method="POST" action="{{ route('brands.trends.preferences', $brand) }}" class="trend-form">
            @csrf
            @method('PUT')
            <label>
                <span class="label">Frequency</span>
                <select name="research_frequency">
                    @foreach (['manual' => 'Manual only', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $key => $label)
                        <option value="{{ $key }}" @selected(($preference?->research_frequency ?? 'manual') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Local research time</span>
                <input type="time" name="research_time" value="{{ $preference?->research_time }}">
            </label>
            <label>
                <span class="label">Time zone</span>
                <input type="text" name="timezone" value="{{ $preference?->timezone ?? 'UTC' }}" required>
            </label>
            <button class="button-secondary" type="submit">Save schedule</button>
        </form>
    </section>

    <section style="margin-top:1rem;">
        <h2>Brand-relevant topics</h2>
        @forelse ($topics as $topic)
            <article class="card panel trend-card">
                <div class="trend-section-heading">
                    <div>
                        <span class="badge badge--pending">{{ \Illuminate\Support\Str::headline($topic->category) }}</span>
                        @if ($topic->momentum)
                            <span class="badge badge--active">{{ \Illuminate\Support\Str::headline($topic->momentum) }}</span>
                        @else
                            <span class="badge badge--pending">Momentum unavailable</span>
                        @endif
                        <h3 style="margin:.7rem 0 .35rem;">{{ $topic->title }}</h3>
                        <p class="muted">{{ $topic->description ?: $topic->summary }}</p>
                    </div>
                    <div class="trend-score">
                        <strong>{{ $topic->relevance_score }}</strong>
                        <span class="muted">Relevance</span>
                    </div>
                </div>
                <p>{{ $topic->relevance_reason }}</p>
                <div class="muted">Opportunity score {{ $topic->opportunity_score }} · {{ $topic->sources->count() }} sources · Researched {{ $topic->researched_at->diffForHumans() }}</div>
                <div class="trend-source-list">
                    @foreach ($topic->sources->take(3) as $source)
                        <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->source_name }}: {{ $source->title }}</a>
                    @endforeach
                </div>
                <div class="trend-actions">
                    <a class="button-secondary" href="{{ route('brands.trends.show', [$brand, $topic]) }}">View evidence</a>
                    @foreach (['relevant' => 'Relevant', 'irrelevant' => 'Not relevant', 'saved' => 'Save'] as $value => $label)
                        <form method="POST" action="{{ route('brands.trends.feedback', [$brand, $topic]) }}">
                            @csrf
                            <input type="hidden" name="feedback" value="{{ $value }}">
                            <button class="button-secondary" type="submit">{{ $label }}</button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ route('brands.trends.feedback', [$brand, $topic]) }}">
                        @csrf
                        <input type="hidden" name="feedback" value="dismissed">
                        <button class="button-secondary" type="submit">Dismiss</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card panel">
                <p class="muted">No researched topics match this brand and filter. Start research with a configured source to discover evidence-backed results.</p>
            </div>
        @endforelse
        <div style="margin-top:1rem;">{{ $topics->links() }}</div>
    </section>
</x-app-layout>
