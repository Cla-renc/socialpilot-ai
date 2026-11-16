<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Visual Content Studio · Phase 7</p>
                <h1>Media Studio</h1>
                <p class="muted">AI-powered visual content generation for social media, marketing campaigns, and video production.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('media.generator') }}" class="button">+ Generate Image</a>
                <a href="{{ route('media.video-studio') }}" class="button-secondary">Video Studio</a>
                <a href="{{ route('media.library') }}" class="button-secondary">Media Library</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1.25rem;border-left:4px solid var(--success);">
            {{ session('success') }}
        </div>
    @endif

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('media.dashboard') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('media.generator') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Image Generator</a>
        <a href="{{ route('media.video-studio') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Video Studio</a>
        <a href="{{ route('media.library') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Media Library</a>
    </div>

    {{-- Failed jobs alert --}}
    @if (($stats['failed_jobs'] ?? 0) > 0)
        <div class="card panel" style="margin-bottom:1.5rem;border-left:4px solid var(--danger);background:rgba(248, 113, 113, 0.08);">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <strong style="color:var(--danger);font-size:1rem;">Attention Required</strong>
                    <p class="muted" style="margin:4px 0 0;">
                        There are {{ $stats['failed_jobs'] }} failed media generation job(s). Check provider quotas or prompt parameters.
                    </p>
                </div>
                <a href="{{ route('media.library', ['asset_type' => 'failed']) }}" class="button-secondary" style="border-color:var(--danger);color:var(--danger);">View in Library</a>
            </div>
        </div>
    @endif

    {{-- Stats Grid (4 cards) --}}
    <section class="dashboard-grid" style="margin-bottom:1.75rem;" aria-label="Media statistics">
        <article class="card stat-card">
            <span class="label">Total Assets</span>
            <div class="value">{{ number_format($stats['total_assets'] ?? 0) }}</div>
            <div class="meta" style="color:var(--primary-2);">All created visual assets</div>
        </article>

        <article class="card stat-card">
            <span class="label">Images Generated</span>
            <div class="value">{{ number_format($stats['images'] ?? 0) }}</div>
            <div class="meta" style="color:var(--success);">High-res visual graphics</div>
        </article>

        <article class="card stat-card">
            <span class="label">Videos</span>
            <div class="value">{{ number_format($stats['videos'] ?? 0) }}</div>
            <div class="meta" style="color:var(--primary);">AI clips & animations</div>
        </article>

        <article class="card stat-card">
            <span class="label">Storyboards</span>
            <div class="value">{{ number_format($stats['storyboards'] ?? 0) }}</div>
            <div class="meta" style="color:var(--warning);">Multi-scene video concepts</div>
        </article>
    </section>

    {{-- Quick Actions & Brand Visual Settings --}}
    <section class="content-grid" style="margin-bottom:1.75rem;">
        <article class="card panel">
            <h2>Quick Actions</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-top:1rem;">
                <a href="{{ route('media.generator') }}" class="card" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;text-decoration:none;background:var(--surface);transition:all 0.2s ease;">
                    <div style="font-size:1.5rem;">🎨</div>
                    <strong style="color:var(--text);">AI Image Generator</strong>
                    <span class="muted" style="font-size:0.85rem;">Create multi-aspect visuals customized to brand identity and platform.</span>
                </a>

                <a href="{{ route('media.video-studio') }}" class="card" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;text-decoration:none;background:var(--surface);transition:all 0.2s ease;">
                    <div style="font-size:1.5rem;">🎬</div>
                    <strong style="color:var(--text);">Video & Storyboard Studio</strong>
                    <span class="muted" style="font-size:0.85rem;">Generate scene-by-scene storyboards and initiate video rendering.</span>
                </a>

                <a href="{{ route('media.library') }}" class="card" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;text-decoration:none;background:var(--surface);transition:all 0.2s ease;">
                    <div style="font-size:1.5rem;">📁</div>
                    <strong style="color:var(--text);">Media Library</strong>
                    <span class="muted" style="font-size:0.85rem;">Browse, filter, preview, and download generated media files.</span>
                </a>
            </div>
        </article>

        <article class="card panel">
            <h2>Brand Visual Settings</h2>
            <p class="muted" style="font-size:0.88rem;margin-bottom:1rem;">
                Configure colors, typography, style guidelines, and logo placements for your brands:
            </p>
            @if ($brands->isEmpty())
                <p class="muted">No active brands found. <a href="{{ route('brands.create') }}">Create a brand</a> first.</p>
            @else
                <div class="list">
                    @foreach ($brands as $brand)
                        <div class="line-item">
                            <div>
                                <strong>{{ $brand->name }}</strong>
                                <div class="muted" style="font-size:0.8rem;">{{ ucfirst($brand->industry ?? 'General') }}</div>
                            </div>
                            <a href="{{ route('brands.visual-settings', $brand) }}" class="button-secondary" style="font-size:0.82rem;padding:0.4rem 0.8rem;">
                                Edit Settings
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>

    {{-- Recent Assets Grid (up to 6 cards) --}}
    <section class="card panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div>
                <h2 style="margin:0;">Recent Assets</h2>
                <p class="muted" style="margin:4px 0 0;font-size:0.88rem;">Latest visual content generated across your brands</p>
            </div>
            <a href="{{ route('media.library') }}" class="button-secondary" style="font-size:0.85rem;">View all in library →</a>
        </div>

        @if ($recent_assets->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;">
                <div style="font-size:2.5rem;margin-bottom:0.75rem;">🖼️</div>
                <h3 style="margin:0 0 0.5rem;">No media assets created yet</h3>
                <p class="muted" style="max-width:450px;margin:0 auto 1.25rem;">
                    Generate social media graphics, quote cards, banners, or storyboards tailored to your brand voice.
                </p>
                <a href="{{ route('media.generator') }}" class="button">Create your first image</a>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1.25rem;">
                @foreach ($recent_assets as $asset)
                    <article class="card" style="overflow:hidden;background:var(--surface);display:flex;flex-direction:column;">
                        {{-- Asset Thumbnail / Preview --}}
                        <div style="position:relative;width:100%;height:180px;background:#030712;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                            @if ($asset->public_url)
                                <img src="{{ $asset->public_url }}" alt="{{ $asset->title ?? 'Media asset' }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                <div style="display:flex;flex-direction:column;align-items:center;gap:6px;color:var(--muted);">
                                    <span style="font-size:2rem;">
                                        {{ $asset->asset_type === 'video' ? '🎬' : ($asset->asset_type === 'storyboard' ? '📋' : '🖼️') }}
                                    </span>
                                    <span style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;">{{ $asset->aspect_ratio ?? '1:1' }}</span>
                                </div>
                            @endif

                            {{-- Badges overlay --}}
                            <div style="position:absolute;top:8px;left:8px;display:flex;gap:4px;">
                                <span class="badge" style="background:rgba(15,23,42,0.85);backdrop-filter:blur(4px);font-size:0.7rem;padding:0.25rem 0.5rem;min-width:auto;">
                                    {{ strtoupper($asset->asset_type) }}
                                </span>
                            </div>

                            <div style="position:absolute;top:8px;right:8px;">
                                @if ($asset->generation_status === 'completed')
                                    <span class="badge badge--success" style="font-size:0.7rem;padding:0.25rem 0.5rem;min-width:auto;">Done</span>
                                @elseif ($asset->generation_status === 'failed')
                                    <span class="badge badge--danger" style="font-size:0.7rem;padding:0.25rem 0.5rem;min-width:auto;">Failed</span>
                                @else
                                    <span class="badge badge--pending" style="font-size:0.7rem;padding:0.25rem 0.5rem;min-width:auto;">{{ ucfirst($asset->generation_status) }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Card content --}}
                        <div style="padding:1rem;flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;">
                            <div>
                                <strong style="display:block;font-size:0.95rem;margin-bottom:0.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $asset->title ?? 'Untitled Asset' }}">
                                    {{ $asset->title ?: 'Untitled Asset' }}
                                </strong>
                                <div class="muted" style="font-size:0.8rem;display:flex;justify-content:space-between;align-items:center;">
                                    <span>{{ $asset->brand?->name ?? 'Brand N/A' }}</span>
                                    <span>{{ $asset->platform ? ucfirst($asset->platform) : 'Multi-platform' }}</span>
                                </div>
                            </div>

                            <div style="display:flex;justify-content:space-between;align-items:center;padding-top:0.5rem;border-top:1px solid var(--stroke);font-size:0.78rem;" class="muted">
                                <span>{{ $asset->created_at ? $asset->created_at->diffForHumans() : 'Recently' }}</span>
                                <a href="{{ route('media.library', ['search' => $asset->title]) }}" class="button-secondary" style="padding:0.25rem 0.6rem;font-size:0.75rem;">View</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-app-layout>
