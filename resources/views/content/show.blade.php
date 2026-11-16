<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ $brand->name }} · Content draft</p>
                <h1>{{ $draft->title ?: 'Untitled draft' }}</h1>
                <p class="muted">
                    {{ ucfirst($draft->platform ?? 'General') }}
                    @if ($draft->objective) · {{ Str::headline($draft->objective) }} @endif
                    · {{ $draft->created_at->format('M j, Y g:i A') }}
                </p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.content.index', $brand) }}" class="button-secondary">Library</a>
                <a href="{{ route('brands.content.studio', $brand) }}" class="button-secondary">New content</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1rem;" id="flash-msg">{{ session('success') }}</div>
    @endif

    <section class="content-grid" id="draft-grid" style="align-items:start;">

        {{-- ── Left: Content editor ── --}}
        <article class="card panel" style="grid-column:1/2;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
                <h2 style="margin:0;">Content</h2>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <span class="badge" id="status-badge">{{ ucfirst($draft->status) }}</span>
                    @if ($draft->is_ai_generated)
                        <span class="badge badge--pending">AI generated</span>
                    @endif
                    @if ($draft->quality_score !== null)
                        <span class="badge {{ $draft->quality_score >= 75 ? 'badge--success' : ($draft->quality_score >= 50 ? 'badge--pending' : 'badge--danger') }}">
                            Quality: {{ $draft->quality_score }}/100
                        </span>
                    @endif
                </div>
            </div>

            <form id="edit-form" style="display:flex;flex-direction:column;gap:1rem;">
                @csrf
                <label>
                    <span class="label">Title</span>
                    <input type="text" id="f-title" name="title" value="{{ $draft->title }}" style="width:100%;">
                </label>

                <label>
                    <span class="label">Hook</span>
                    <textarea id="f-hook" name="hook" rows="2" style="width:100%;resize:vertical;">{{ $draft->hook }}</textarea>
                </label>

                <label>
                    <span class="label">Body</span>
                    <textarea id="f-body" name="body" rows="10" style="width:100%;resize:vertical;">{{ $draft->body }}</textarea>
                </label>

                <label>
                    <span class="label">Caption</span>
                    <textarea id="f-caption" name="caption" rows="2" style="width:100%;resize:vertical;">{{ $draft->caption }}</textarea>
                </label>

                <label>
                    <span class="label">Call to action</span>
                    <input type="text" id="f-cta" name="call_to_action" value="{{ $draft->call_to_action }}" style="width:100%;">
                </label>

                <label>
                    <span class="label">Hashtags</span>
                    <input type="text" id="f-hashtags" name="hashtags" value="{{ is_array($draft->hashtags) ? implode(' ', $draft->hashtags) : $draft->hashtags }}" style="width:100%;">
                </label>

                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <button type="button" class="button" onclick="saveDraft()">Save changes</button>
                    <button type="button" class="button-secondary" onclick="copyAll()">Copy content</button>
                    <button type="button" class="button-secondary" onclick="deleteDraft()" style="color:#c44;">Delete</button>
                </div>
                <p class="muted" id="save-status" style="font-size:0.85rem;display:none;"></p>
            </form>
        </article>

        {{-- ── Right: Actions, variations, revisions ── --}}
        <div style="display:flex;flex-direction:column;gap:1rem;grid-column:2/3;">

            {{-- Improvement tools --}}
            <article class="card panel">
                <h2>Improve</h2>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    @foreach([
                        ['shorten',         'Shorten',         'Remove unnecessary words and tighten the content.'],
                        ['expand',          'Expand',          'Add more depth, examples, or detail.'],
                        ['improve_hook',    'Improve hook',    'Rewrite the opening hook to be more compelling.'],
                        ['better_cta',      'Stronger CTA',    'Generate a more effective call to action.'],
                        ['add_storytelling','Add storytelling','Reframe using a narrative structure.'],
                        ['professional',    'Make professional','Raise the tone for a business audience.'],
                        ['conversational',  'Make conversational','Rewrite in a friendly, natural voice.'],
                    ] as [$op, $label, $desc])
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
                            <div>
                                <strong style="font-size:0.9rem;">{{ $label }}</strong>
                                <p class="muted" style="font-size:0.8rem;margin:0;">{{ $desc }}</p>
                            </div>
                            <button class="button-secondary" style="flex-shrink:0;font-size:0.85rem;"
                                onclick="improveContent('{{ $op }}')">Apply</button>
                        </div>
                    @endforeach
                </div>
                <p class="muted" id="improve-status" style="font-size:0.85rem;margin-top:0.75rem;display:none;"></p>
            </article>

            {{-- Platform adapt --}}
            <article class="card panel">
                <h2>Adapt for platform</h2>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                    @foreach(['linkedin','instagram','tiktok','youtube','facebook'] as $p)
                        @if ($p !== $draft->platform)
                            <button class="button-secondary" style="font-size:0.85rem;"
                                onclick="adaptPlatform('{{ $p }}')">{{ ucfirst($p) }}</button>
                        @endif
                    @endforeach
                </div>
                <p class="muted" id="adapt-status" style="font-size:0.85rem;margin-top:0.75rem;display:none;"></p>
            </article>

            {{-- Variations --}}
            <article class="card panel">
                <h2>Variations</h2>
                @if ($draft->variations->isNotEmpty())
                    <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1rem;" id="variations-list">
                        @foreach ($draft->variations as $v)
                            <div class="card" style="padding:0.75rem;border:1px solid #e0e0e0;border-radius:6px;" id="var-{{ $v->id }}">
                                <div style="display:flex;justify-content:space-between;align-items:start;gap:0.5rem;margin-bottom:0.5rem;">
                                    <strong style="font-size:0.9rem;">{{ Str::headline($v->style ?? 'variation') }}</strong>
                                    <div style="display:flex;gap:0.5rem;flex-shrink:0;">
                                        @if ($v->is_selected)
                                            <span class="badge badge--success">Selected</span>
                                        @else
                                            <button class="button-secondary" style="font-size:0.8rem;"
                                                onclick="selectVariation({{ $v->id }})">Use this</button>
                                        @endif
                                    </div>
                                </div>
                                @if ($v->hook)
                                    <p style="font-size:0.85rem;font-style:italic;margin:0 0 0.35rem;" class="muted">{{ Str::limit($v->hook, 100) }}</p>
                                @endif
                                <p style="font-size:0.85rem;margin:0;">{{ Str::limit($v->body, 200) }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted" style="margin-bottom:1rem;">No variations generated yet.</p>
                @endif
                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <button class="button-secondary" onclick="generateVariations(2)">Generate 2 variations</button>
                    <button class="button-secondary" onclick="generateVariations(3)">Generate 3</button>
                    <span class="muted" id="var-status" style="font-size:0.85rem;display:none;">Working…</span>
                </div>
            </article>

            {{-- Trend sources --}}
            @if ($draft->trendSources->isNotEmpty())
            <article class="card panel">
                <h2>Trend references</h2>
                <div class="list">
                    @foreach ($draft->trendSources as $src)
                        @if ($src->trendingTopic)
                            <div class="line-item">
                                <span class="muted">Trend</span>
                                <a href="{{ route('brands.trends.show', [$brand, $src->trendingTopic]) }}">
                                    {{ Str::limit($src->trendingTopic->title, 60) }}
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            </article>
            @endif

            {{-- Quality evaluation --}}
            @if ($draft->quality_evaluation)
            <article class="card panel">
                <h2>Quality evaluation</h2>
                <p class="muted" style="font-size:0.82rem;">Structural assessment. Does not predict virality or audience performance.</p>
                @php $eval = is_array($draft->quality_evaluation) ? $draft->quality_evaluation : (array)json_decode($draft->quality_evaluation, true); @endphp
                @if (!empty($eval['criteria']))
                    <div style="display:flex;flex-direction:column;gap:0.3rem;margin-top:0.75rem;">
                        @foreach ($eval['criteria'] as $key => $score)
                            <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                                <span>{{ Str::headline(str_replace('_',' ',$key)) }}</span>
                                <strong>{{ $score }}/10</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if (!empty($eval['issues']))
                    <div style="margin-top:0.75rem;">
                        @foreach ($eval['issues'] as $issue)
                            <p style="font-size:0.85rem;color:#c44;margin:0.2rem 0;">⚠ {{ $issue }}</p>
                        @endforeach
                    </div>
                @endif
                @if (!empty($eval['suggestions']))
                    <div style="margin-top:0.5rem;">
                        @foreach ($eval['suggestions'] as $sug)
                            <p style="font-size:0.85rem;color:#666;margin:0.2rem 0;">💡 {{ $sug }}</p>
                        @endforeach
                    </div>
                @endif
            </article>
            @endif

            {{-- Revision history --}}
            <article class="card panel">
                <h2>Revision history</h2>
                @if ($draft->revisions->isNotEmpty())
                    <div class="list" id="revisions-list">
                        @foreach ($draft->revisions->sortByDesc('created_at') as $rev)
                            <div class="line-item">
                                <span class="muted" style="font-size:0.85rem;">{{ Str::headline($rev->action) }}</span>
                                <span style="font-size:0.82rem;">{{ $rev->created_at->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No revisions recorded yet.</p>
                @endif
            </article>
        </div>
    </section>

    <style>
        textarea { font-family: inherit; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; }
        input[type="text"] { padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; }
        #draft-grid { grid-template-columns: 1fr 1fr; }
        @media (max-width: 900px) {
            #draft-grid { grid-template-columns: 1fr; }
            #draft-grid > div { grid-column: 1 / 2; }
        }
    </style>

    <script>
    const BRAND_ID = {{ $brand->id }};
    const DRAFT_ID = {{ $draft->id }};
    const CSRF    = document.querySelector('meta[name="csrf-token"]').content;

    function apiHeaders() {
        return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF };
    }

    async function saveDraft() {
        const payload = {
            title:         document.getElementById('f-title').value,
            hook:          document.getElementById('f-hook').value,
            body:          document.getElementById('f-body').value,
            caption:       document.getElementById('f-caption').value,
            call_to_action:document.getElementById('f-cta').value,
            hashtags:      document.getElementById('f-hashtags').value,
        };

        const st = document.getElementById('save-status');
        st.style.display = '';
        st.textContent = 'Saving…';

        try {
            const res = await fetch(`/api/v1/content/${DRAFT_ID}`, {
                method: 'PUT',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            if (res.ok) {
                st.textContent = '✓ Saved';
                setTimeout(() => { st.style.display = 'none'; }, 2500);
            } else {
                const j = await res.json();
                st.textContent = j.message || 'Save failed.';
            }
        } catch (e) {
            st.textContent = 'Network error.';
        }
    }

    async function improveContent(operation) {
        const st = document.getElementById('improve-status');
        st.style.display = '';
        st.textContent = 'Applying…';

        try {
            const res = await fetch(`/api/v1/content/${DRAFT_ID}/improve`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ operation }),
            });
            const j = await res.json();
            if (res.ok && j.data) {
                const d = j.data;
                if (d.hook)          document.getElementById('f-hook').value    = d.hook;
                if (d.body)          document.getElementById('f-body').value    = d.body;
                if (d.caption)       document.getElementById('f-caption').value = d.caption;
                if (d.call_to_action)document.getElementById('f-cta').value    = d.call_to_action;
                st.textContent = '✓ Applied — review and save changes.';
                setTimeout(() => { st.style.display = 'none'; }, 4000);
            } else {
                st.textContent = j.message || 'Improvement failed.';
            }
        } catch (e) {
            st.textContent = 'Network error.';
        }
    }

    async function adaptPlatform(platform) {
        const st = document.getElementById('adapt-status');
        st.style.display = '';
        st.textContent = `Adapting for ${platform}…`;

        try {
            const res = await fetch(`/api/v1/content/${DRAFT_ID}/improve`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ operation: 'adapt_platform', target_platform: platform }),
            });
            const j = await res.json();
            if (res.ok && j.data) {
                const d = j.data;
                if (d.hook)    document.getElementById('f-hook').value    = d.hook;
                if (d.body)    document.getElementById('f-body').value    = d.body;
                if (d.caption) document.getElementById('f-caption').value = d.caption;
                st.textContent = `✓ Adapted for ${platform} — review and save changes.`;
                setTimeout(() => { st.style.display = 'none'; }, 4000);
            } else {
                st.textContent = j.message || 'Adaptation failed.';
            }
        } catch (e) {
            st.textContent = 'Network error.';
        }
    }

    async function generateVariations(count) {
        const st = document.getElementById('var-status');
        st.style.display = '';
        st.textContent = `Generating ${count} variations…`;

        try {
            const res = await fetch(`/api/v1/content/${DRAFT_ID}/variations`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ count }),
            });
            const j = await res.json();
            if (res.ok) {
                st.textContent = `✓ ${j.data.length} variations generated — reload to view.`;
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                st.textContent = j.message || 'Failed to generate variations.';
            }
        } catch (e) {
            st.textContent = 'Network error.';
        }
    }

    async function selectVariation(variationId) {
        try {
            const res = await fetch(`/api/v1/content/${DRAFT_ID}/variations/${variationId}/select`, {
                method: 'POST',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            if (res.ok) {
                window.location.reload();
            } else {
                alert('Could not select variation.');
            }
        } catch (e) {
            alert('Network error.');
        }
    }

    async function deleteDraft() {
        if (!confirm('Delete this draft permanently? This cannot be undone.')) return;
        try {
            const res = await fetch(`/api/v1/content/${DRAFT_ID}`, {
                method: 'DELETE',
                headers: apiHeaders(),
                credentials: 'same-origin',
            });
            if (res.ok) {
                window.location.href = `/brands/${BRAND_ID}/content`;
            } else {
                alert('Delete failed.');
            }
        } catch (e) {
            alert('Network error.');
        }
    }

    function copyAll() {
        const parts = [
            document.getElementById('f-hook').value,
            document.getElementById('f-body').value,
            document.getElementById('f-caption').value,
            document.getElementById('f-cta').value,
            document.getElementById('f-hashtags').value,
        ].filter(Boolean);
        navigator.clipboard.writeText(parts.join('\n\n'));
    }
    </script>
</x-app-layout>
