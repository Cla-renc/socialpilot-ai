<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">AI Scheduling Intelligence · Phase 8</p>
                <h1>Best Time to Post Recommendations</h1>
                <p class="muted">Data-driven posting schedule suggestions for <strong>{{ $brand->name }}</strong> based on category heuristics and historical records.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.scheduling.schedule', $brand) }}" class="button">+ Schedule Post</a>
                <a href="{{ route('scheduling.calendar') }}" class="button-secondary">Calendar</a>
            </div>
        </div>
    </x-slot>

    {{-- Platform Selector Tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        @foreach(['instagram', 'facebook', 'tiktok', 'youtube', 'linkedin'] as $p)
            <button type="button" class="button-secondary platform-tab" data-platform="{{ $p }}" onclick="selectPlatform('{{ $p }}')" style="padding:0.45rem 1rem;font-size:0.88rem;text-transform:capitalize;">
                {{ $p }}
            </button>
        @endforeach
    </div>

    {{-- Notice card: Heuristics vs Historical --}}
    <div class="card panel" style="margin-bottom:1.5rem;border-left:4px solid var(--primary, #4f46e5);background:rgba(79, 70, 229, 0.04);">
        <strong style="color:var(--primary, #4f46e5);font-size:0.95rem;">Recommendation Methodology</strong>
        <p class="muted" style="margin:0.25rem 0 0;font-size:0.88rem;">
            Recommendations utilize brand audience archetypes, platform activity windows, and historical post data when available. Where historical records are limited, calibrated industry heuristics are clearly labelled as preliminary recommendations.
        </p>
    </div>

    <div id="loadingIndicator" style="display:none;text-align:center;padding:2rem;" class="muted">
        Computing optimal publishing windows...
    </div>

    {{-- Recommendations Grid --}}
    <div id="recommendationsGrid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;"></div>

    <script>
        const brandId = {{ $brand->id }};
        let activePlatform = 'instagram';

        async function loadRecommendations(platform) {
            const container = document.getElementById('recommendationsGrid');
            const loader = document.getElementById('loadingIndicator');
            container.innerHTML = '';
            loader.style.display = 'block';

            // Update tab styles
            document.querySelectorAll('.platform-tab').forEach(tab => {
                if (tab.getAttribute('data-platform') === platform) {
                    tab.classList.remove('button-secondary');
                    tab.classList.add('button');
                } else {
                    tab.classList.remove('button');
                    tab.classList.add('button-secondary');
                }
            });

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/scheduling/best-times?platform=${platform}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                const items = json.data || [];

                loader.style.display = 'none';

                if (items.length === 0) {
                    container.innerHTML = `<div class="card panel" style="grid-column:1/-1;text-align:center;"><p class="muted">No recommendations generated for ${platform}.</p></div>`;
                    return;
                }

                items.forEach(rec => {
                    const card = document.createElement('div');
                    card.className = 'card panel';
                    card.style.display = 'flex';
                    card.style.flexDirection = 'column';
                    card.style.justifyContent = 'space-between';

                    const isHistorical = (rec.evidence_type || '').toLowerCase().includes('historical');
                    const badgeBg = isHistorical ? 'rgba(16, 185, 129, 0.12)' : 'rgba(245, 158, 11, 0.12)';
                    const badgeColor = isHistorical ? '#059669' : '#d97706';
                    const badgeLabel = isHistorical ? 'Historical' : 'Heuristic Estimate';

                    card.innerHTML = `
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                <span style="font-weight:700;font-size:1.1rem;">${rec.day_of_week}</span>
                                <span style="font-size:0.75rem;padding:0.2rem 0.5rem;border-radius:10px;background:${badgeBg};color:${badgeColor};font-weight:600;">
                                    ${badgeLabel}
                                </span>
                            </div>
                            <div style="font-size:1.75rem;font-weight:700;color:var(--primary, #4f46e5);margin:0.25rem 0;">
                                ${rec.suggested_time}
                            </div>
                            <div class="muted" style="font-size:0.8rem;margin-bottom:0.75rem;">Timezone: ${rec.timezone || 'UTC'}</div>
                            <p style="font-size:0.88rem;margin:0;line-height:1.4;">${rec.reason}</p>
                        </div>
                        <div style="margin-top:1.25rem;border-top:1px solid var(--stroke, #e5e7eb);padding-top:0.75rem;display:flex;justify-content:space-between;align-items:center;">
                            <span class="muted" style="font-size:0.75rem;">Score: ${Math.round((rec.confidence_score || 0.8) * 100)}%</span>
                            <a href="/brands/${brandId}/scheduling/schedule" class="button-secondary" style="font-size:0.8rem;padding:0.3rem 0.6rem;">Schedule at this time</a>
                        </div>
                    `;

                    container.appendChild(card);
                });
            } catch (err) {
                loader.style.display = 'none';
                container.innerHTML = `<div class="card panel" style="grid-column:1/-1;text-align:center;"><p class="muted">Could not load recommendations: ${err.message}</p></div>`;
            }
        }

        function selectPlatform(p) {
            activePlatform = p;
            loadRecommendations(p);
        }

        document.addEventListener('DOMContentLoaded', () => {
            selectPlatform('instagram');
        });
    </script>
</x-app-layout>
