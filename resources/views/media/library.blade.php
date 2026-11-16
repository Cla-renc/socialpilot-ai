<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Visual Studio · Asset Management</p>
                <h1>Media Library</h1>
                <p class="muted">Browse, preview, filter, and manage all AI-generated images, videos, and graphics.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('media.generator') }}" class="button">+ Generate Image</a>
                <a href="{{ route('media.video-studio') }}" class="button-secondary">Video Studio</a>
                <a href="{{ route('media.dashboard') }}" class="button-secondary">Dashboard</a>
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
        <a href="{{ route('media.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('media.generator') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Image Generator</a>
        <a href="{{ route('media.video-studio') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Video Studio</a>
        <a href="{{ route('media.library') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Media Library</a>
    </div>

    {{-- Filter Bar --}}
    <section class="card panel" style="margin-bottom:1.5rem;">
        <form method="GET" action="{{ route('media.library') }}" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;">
            {{-- Brand filter --}}
            <label style="flex:1 1 180px;">
                <span class="label">Brand</span>
                <select name="brand_id" style="width:100%;">
                    <option value="">All Brands</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(($filters['brand_id'] ?? '') == $brand->id)>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            {{-- Asset Type filter --}}
            <label style="flex:1 1 140px;">
                <span class="label">Asset Type</span>
                <select name="asset_type" style="width:100%;">
                    <option value="">All Types</option>
                    <option value="image" @selected(($filters['asset_type'] ?? '') === 'image')>Image</option>
                    <option value="video" @selected(($filters['asset_type'] ?? '') === 'video')>Video</option>
                    <option value="storyboard" @selected(($filters['asset_type'] ?? '') === 'storyboard')>Storyboard</option>
                    <option value="poster" @selected(($filters['asset_type'] ?? '') === 'poster')>Poster</option>
                    <option value="graphic" @selected(($filters['asset_type'] ?? '') === 'graphic')>Graphic</option>
                    <option value="thumbnail" @selected(($filters['asset_type'] ?? '') === 'thumbnail')>Thumbnail</option>
                </select>
            </label>

            {{-- Platform filter --}}
            <label style="flex:1 1 140px;">
                <span class="label">Platform</span>
                <select name="platform" style="width:100%;">
                    <option value="">All Platforms</option>
                    @foreach (['instagram', 'facebook', 'tiktok', 'youtube', 'linkedin'] as $p)
                        <option value="{{ $p }}" @selected(($filters['platform'] ?? '') === $p)>
                            {{ ucfirst($p) }}
                        </option>
                    @endforeach
                </select>
            </label>

            {{-- Search input --}}
            <label style="flex:2 1 200px;">
                <span class="label">Search</span>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by title, prompt, filename..." style="width:100%;">
            </label>

            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button type="submit" class="button">Filter</button>
                @if (request()->hasAny(['brand_id', 'asset_type', 'platform', 'search']))
                    <a href="{{ route('media.library') }}" class="button-secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    {{-- Assets Grid --}}
    @if ($assets->isEmpty())
        <article class="card panel" style="text-align:center;padding:3.5rem 1rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">🖼️</div>
            <h2 style="margin:0 0 0.5rem;">No media assets found</h2>
            <p class="muted" style="max-width:400px;margin:0 auto 1.5rem;">
                @if (request()->hasAny(['brand_id', 'asset_type', 'platform', 'search']))
                    No assets matched your filter criteria. Try adjusting your search query or reset the filters.
                @else
                    You have not generated any media assets yet. Create high-impact images and videos tailored for your brands.
                @endif
            </p>
            @if (request()->hasAny(['brand_id', 'asset_type', 'platform', 'search']))
                <a href="{{ route('media.library') }}" class="button-secondary">Clear Filters</a>
            @else
                <a href="{{ route('media.generator') }}" class="button">Create AI Image</a>
            @endif
        </article>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1.25rem;margin-bottom:1.5rem;" id="media-grid">
            @foreach ($assets as $asset)
                <article class="card asset-item-card" id="asset-card-{{ $asset->id }}" style="overflow:hidden;background:var(--surface);display:flex;flex-direction:column;transition:transform 0.15s ease;">
                    {{-- Thumbnail Box --}}
                    <div style="position:relative;width:100%;height:190px;background:#030712;display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;" onclick="openPreview({{ json_encode($asset) }})">
                        @if ($asset->public_url)
                            <img src="{{ $asset->public_url }}" alt="{{ $asset->title ?? 'Media Asset' }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;color:var(--muted);">
                                <span style="font-size:2.2rem;">
                                    @if ($asset->asset_type === 'video') 🎬
                                    @elseif ($asset->asset_type === 'storyboard') 📋
                                    @elseif ($asset->asset_type === 'poster') 📜
                                    @else 🖼️
                                    @endif
                                </span>
                                <span style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;">{{ $asset->aspect_ratio ?? '1:1' }}</span>
                            </div>
                        @endif

                        {{-- Type badge --}}
                        <div style="position:absolute;top:8px;left:8px;display:flex;gap:4px;">
                            <span class="badge" style="background:rgba(15,23,42,0.85);backdrop-filter:blur(4px);font-size:0.7rem;padding:0.25rem 0.55rem;min-width:auto;
                                @if($asset->asset_type === 'image') color:var(--primary-2);
                                @elseif($asset->asset_type === 'video') color:var(--primary);
                                @elseif($asset->asset_type === 'storyboard') color:var(--warning);
                                @endif">
                                {{ strtoupper($asset->asset_type) }}
                            </span>
                        </div>

                        {{-- Status badge --}}
                        <div style="position:absolute;top:8px;right:8px;">
                            @if ($asset->generation_status === 'completed')
                                <span class="badge badge--success" style="font-size:0.7rem;padding:0.2rem 0.5rem;min-width:auto;">Done</span>
                            @elseif ($asset->generation_status === 'failed')
                                <span class="badge badge--danger" style="font-size:0.7rem;padding:0.2rem 0.5rem;min-width:auto;">Failed</span>
                            @else
                                <span class="badge badge--pending" style="font-size:0.7rem;padding:0.2rem 0.5rem;min-width:auto;">{{ ucfirst($asset->generation_status) }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Body --}}
                    <div style="padding:1rem;flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:0.75rem;">
                        <div>
                            <strong style="display:block;font-size:0.95rem;margin-bottom:0.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $asset->title ?: 'Untitled asset' }}">
                                {{ $asset->title ?: 'Untitled asset' }}
                            </strong>
                            <div class="muted" style="font-size:0.8rem;display:flex;justify-content:space-between;">
                                <span>{{ $asset->brand?->name ?? 'Brand N/A' }}</span>
                                <span>{{ $asset->platform ? ucfirst($asset->platform) : ($asset->aspect_ratio ?: '1:1') }}</span>
                            </div>
                            @if ($asset->generation_prompt)
                                <p class="muted" style="font-size:0.75rem;margin:0.4rem 0 0;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    {{ $asset->generation_prompt }}
                                </p>
                            @endif
                        </div>

                        {{-- Card footer / Actions --}}
                        <div style="padding-top:0.6rem;border-top:1px solid var(--stroke);display:flex;justify-content:space-between;align-items:center;">
                            <span class="muted" style="font-size:0.75rem;">{{ $asset->created_at ? $asset->created_at->format('M j, Y') : 'Recent' }}</span>
                            <div style="display:flex;gap:0.4rem;align-items:center;">
                                <button type="button" class="button-secondary" style="padding:0.25rem 0.6rem;font-size:0.75rem;" onclick="openPreview({{ json_encode($asset) }})">
                                    Preview
                                </button>
                                @if ($asset->public_url)
                                    <a href="{{ $asset->public_url }}" download class="button-secondary" style="padding:0.25rem 0.6rem;font-size:0.75rem;" title="Download asset">
                                        ⬇
                                    </a>
                                @endif
                                <button type="button" class="button-secondary" style="padding:0.25rem 0.5rem;font-size:0.75rem;color:var(--danger);border-color:rgba(248,113,113,0.3);" onclick="deleteAsset({{ $asset->id }}, '{{ addslashes($asset->title ?? 'this asset') }}')" title="Delete asset">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div style="margin-top:1.5rem;display:flex;justify-content:center;">
            {{ $assets->links() }}
        </div>
    @endif

    {{-- Preview Modal --}}
    <div id="preview-modal" style="display:none;position:fixed;inset:0;background:rgba(3,7,18,0.85);backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:1.5rem;" onclick="closeModalOnBackdrop(event)">
        <div class="card panel" style="width:100%;max-width:850px;max-height:90vh;overflow-y:auto;position:relative;background:var(--panel);box-shadow:0 25px 50px -12px rgba(0,0,0,0.7);border:1px solid var(--stroke);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;">
                <div>
                    <h2 id="modal-title" style="margin:0;font-size:1.25rem;">Asset Preview</h2>
                    <span id="modal-subtitle" class="muted" style="font-size:0.8rem;"></span>
                </div>
                <button type="button" onclick="closeModal()" class="button-secondary" style="padding:0.3rem 0.7rem;font-size:1rem;line-height:1;">✕</button>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;" id="modal-grid">
                {{-- Media Preview Image --}}
                <div style="background:#030712;border-radius:12px;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:300px;border:1px solid var(--stroke);">
                    <img id="modal-image" src="" alt="Full Preview" style="width:100%;max-height:450px;object-fit:contain;display:block;">
                    <div id="modal-fallback" style="display:none;padding:2rem;text-align:center;color:var(--muted);">
                        <span style="font-size:3rem;display:block;margin-bottom:0.5rem;">🎬</span>
                        <span>Video / Media rendering placeholder</span>
                    </div>
                </div>

                {{-- Asset Metadata --}}
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    <div class="list">
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Asset Type</span>
                            <span id="modal-type" class="badge" style="min-width:auto;padding:0.2rem 0.6rem;"></span>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Status</span>
                            <span id="modal-status" class="badge" style="min-width:auto;padding:0.2rem 0.6rem;"></span>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Aspect Ratio</span>
                            <strong id="modal-ratio"></strong>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Platform</span>
                            <span id="modal-platform"></span>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">AI Provider</span>
                            <span id="modal-provider" class="muted"></span>
                        </div>
                        <div class="line-item" style="padding:8px 12px;font-size:0.85rem;">
                            <span class="muted">Created</span>
                            <span id="modal-date" class="muted"></span>
                        </div>
                    </div>

                    <div>
                        <span class="label" style="display:block;margin-bottom:0.35rem;">Generation Prompt</span>
                        <div id="modal-prompt" style="padding:0.75rem;border-radius:8px;background:rgba(15,23,42,0.6);border:1px solid var(--stroke);font-size:0.82rem;line-height:1.4;white-space:pre-wrap;max-height:120px;overflow-y:auto;"></div>
                    </div>

                    <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
                        <a id="modal-download" href="#" target="_blank" download class="button" style="flex:1;text-align:center;">
                            ⬇ Download File
                        </a>
                        <button type="button" onclick="closeModal()" class="button-secondary">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .asset-item-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }
        @media (max-width: 768px) {
            #modal-grid { grid-template-columns: 1fr !important; }
        }
    </style>

    <script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function openPreview(asset) {
        const modal = document.getElementById('preview-modal');
        const titleEl = document.getElementById('modal-title');
        const subtitleEl = document.getElementById('modal-subtitle');
        const imageEl = document.getElementById('modal-image');
        const fallbackEl = document.getElementById('modal-fallback');
        const typeEl = document.getElementById('modal-type');
        const statusEl = document.getElementById('modal-status');
        const ratioEl = document.getElementById('modal-ratio');
        const platformEl = document.getElementById('modal-platform');
        const providerEl = document.getElementById('modal-provider');
        const dateEl = document.getElementById('modal-date');
        const promptEl = document.getElementById('modal-prompt');
        const downloadBtn = document.getElementById('modal-download');

        titleEl.textContent = asset.title || 'Media Asset Details';
        subtitleEl.textContent = (asset.brand?.name ? asset.brand.name + ' · ' : '') + (asset.file_name || 'asset');

        if (asset.public_url) {
            imageEl.src = asset.public_url;
            imageEl.style.display = 'block';
            fallbackEl.style.display = 'none';
            downloadBtn.href = asset.public_url;
            downloadBtn.style.display = 'inline-block';
        } else {
            imageEl.style.display = 'none';
            fallbackEl.style.display = 'block';
            downloadBtn.style.display = 'none';
        }

        typeEl.textContent = (asset.asset_type || 'image').toUpperCase();
        statusEl.textContent = (asset.generation_status || 'completed').toUpperCase();
        statusEl.className = 'badge ' + (asset.generation_status === 'completed' ? 'badge--success' : (asset.generation_status === 'failed' ? 'badge--danger' : 'badge--pending'));

        ratioEl.textContent = (asset.aspect_ratio || '1:1') + (asset.width ? ` · ${asset.width}x${asset.height}px` : '');
        platformEl.textContent = asset.platform ? (asset.platform.charAt(0).toUpperCase() + asset.platform.slice(1)) : 'All Platforms';
        providerEl.textContent = (asset.provider || 'AI Engine') + (asset.provider_model ? ` (${asset.provider_model})` : '');
        dateEl.textContent = asset.created_at ? new Date(asset.created_at).toLocaleDateString() : 'N/A';
        promptEl.textContent = asset.generation_prompt || 'No generation prompt recorded for this asset.';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modal = document.getElementById('preview-modal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function closeModalOnBackdrop(e) {
        if (e.target.id === 'preview-modal') {
            closeModal();
        }
    }

    async function deleteAsset(assetId, assetTitle) {
        if (!confirm(`Are you sure you want to delete "${assetTitle}"? This cannot be undone.`)) {
            return;
        }

        try {
            const res = await fetch(`/api/v1/media/${assetId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });

            if (res.ok) {
                const card = document.getElementById(`asset-card-${assetId}`);
                if (card) {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => card.remove(), 250);
                } else {
                    window.location.reload();
                }
            } else {
                const data = await res.json().catch(() => ({}));
                alert('Could not delete asset: ' + (data.message || 'Server error.'));
            }
        } catch (err) {
            console.error('Delete failed:', err);
            alert('An error occurred while deleting the asset.');
        }
    }

    // Keyboard ESC to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    </script>
</x-app-layout>
