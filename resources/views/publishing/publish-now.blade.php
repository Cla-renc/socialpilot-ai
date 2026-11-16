<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Publishing Studio · Phase 9</p>
                <h1>Publish Now</h1>
                <p class="muted">Instant cross-platform post dispatch to connected social channels.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('publishing.history') }}" class="button-secondary">History</a>
                <a href="{{ route('publishing.dashboard') }}" class="button-secondary">Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('publishing.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('publishing.publish-now') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Publish Now</a>
        <a href="{{ route('publishing.history') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">History</a>
        <a href="{{ route('publishing.failed') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Failed Attempts</a>
        <a href="{{ route('publishing.notifications') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Notifications</a>
    </div>

    {{-- Status Banner --}}
    <div id="publishAlert" style="display:none;margin-bottom:1.25rem;padding:0.85rem 1.25rem;border-radius:8px;font-size:0.9rem;"></div>

    <div class="card panel" style="padding:2rem;max-width:800px;margin:0 auto 2rem;">
        @if($brands->isEmpty())
            <div style="text-align:center;padding:2.5rem 1rem;" class="muted">
                <p style="font-size:1.1rem;margin-bottom:1rem;">You need to create a brand before publishing posts.</p>
                <a href="{{ route('brands.create') }}" class="button">Create Brand</a>
            </div>
        @else
            <form id="publishNowForm" onsubmit="handlePublishNow(event)" style="display:flex;flex-direction:column;gap:1.5rem;">
                {{-- Brand Selection --}}
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <label for="brand_select" style="font-weight:600;font-size:0.92rem;color:var(--text);">Select Brand <span style="color:var(--danger, #f87171);">*</span></label>
                    <select id="brand_select" name="brand_id" onchange="onBrandChange(this.value)" required style="padding:0.65rem 0.85rem;border-radius:8px;background:var(--surface);border:1px solid var(--stroke);color:var(--text);font-size:0.95rem;">
                        <option value="">-- Choose Brand --</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }} ({{ $brand->socialAccounts->count() }} accounts)</option>
                        @endforeach
                    </select>
                </div>

                {{-- Social Accounts Checkboxes Container --}}
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <label style="font-weight:600;font-size:0.92rem;color:var(--text);">Target Social Account <span style="color:var(--danger, #f87171);">*</span></label>
                    <div id="accountsContainer" style="min-height:70px;background:var(--surface);border:1px solid var(--stroke);border-radius:8px;padding:1rem;display:flex;flex-direction:column;gap:0.75rem;">
                        <span class="muted" style="font-size:0.88rem;">Please select a brand above to view connected social accounts.</span>
                    </div>
                </div>

                {{-- Content Textarea --}}
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <label for="content_textarea" style="font-weight:600;font-size:0.92rem;color:var(--text);">Post Content <span style="color:var(--danger, #f87171);">*</span></label>
                        <span id="charCount" class="muted" style="font-size:0.8rem;">0 / 5000 characters</span>
                    </div>
                    <textarea id="content_textarea" name="content" required rows="6" maxlength="5000"
                              oninput="document.getElementById('charCount').textContent = `${this.value.length} / 5000 characters`"
                              placeholder="Write your post caption, hashtags, and message here..."
                              style="padding:0.75rem;border-radius:8px;background:var(--surface);border:1px solid var(--stroke);color:var(--text);font-size:0.95rem;line-height:1.5;resize:vertical;"></textarea>
                </div>

                {{-- Submit Button --}}
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:1rem;margin-top:0.5rem;">
                    <a href="{{ route('publishing.dashboard') }}" class="button-secondary">Cancel</a>
                    <button type="submit" id="submitBtn" class="button" style="padding:0.65rem 1.75rem;font-size:0.95rem;font-weight:600;">
                        Publish Now &rarr;
                    </button>
                </div>
            </form>
        @endif
    </div>

    <script>
        // Eager-loaded brands from server
        const brandsData = @json($brands);

        function onBrandChange(brandId) {
            const container = document.getElementById('accountsContainer');
            if (!brandId) {
                container.innerHTML = '<span class="muted" style="font-size:0.88rem;">Please select a brand above to view connected social accounts.</span>';
                return;
            }

            const brand = brandsData.find(b => b.id == brandId);
            const accounts = (brand && brand.social_accounts) ? brand.social_accounts : [];

            if (accounts.length === 0) {
                container.innerHTML = `
                    <div class="muted" style="font-size:0.88rem;padding:0.5rem 0;">
                        No connected social accounts found for this brand. 
                        <a href="/brands/${brandId}/social-accounts" style="color:var(--primary-2, #22d3ee);text-decoration:underline;">Connect accounts now &rarr;</a>
                    </div>
                `;
                return;
            }

            let html = '';
            accounts.forEach((acc, index) => {
                const isChecked = index === 0 ? 'checked' : '';
                const platform = acc.provider || acc.platform || 'Social';
                const name = acc.display_name || acc.account_name || acc.provider_account_id || `Account #${acc.id}`;
                const status = acc.status || 'connected';

                html += `
                    <label style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0.85rem;border-radius:6px;background:rgba(255,255,255,0.03);border:1px solid var(--stroke);cursor:pointer;">
                        <input type="radio" name="social_account_id" value="${acc.id}" ${isChecked} required style="accent-color:var(--primary, #8b5cf6);width:16px;height:16px;">
                        <div style="flex:1;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span style="display:inline-block;padding:0.2rem 0.55rem;border-radius:4px;font-size:0.75rem;font-weight:700;background:rgba(255,255,255,0.08);text-transform:capitalize;">
                                    ${platform}
                                </span>
                                <span style="font-size:0.9rem;font-weight:600;color:var(--text);">${name}</span>
                            </div>
                            <span style="font-size:0.75rem;color:${status === 'connected' ? '#34d399' : 'var(--muted)'};text-transform:capitalize;">
                                ● ${status}
                            </span>
                        </div>
                    </label>
                `;
            });

            container.innerHTML = html;
        }

        // Auto-select first brand if present
        document.addEventListener('DOMContentLoaded', () => {
            const select = document.getElementById('brand_select');
            if (select && select.options.length > 1) {
                select.selectedIndex = 1;
                onBrandChange(select.value);
            }
        });

        async function handlePublishNow(e) {
            e.preventDefault();

            const form = document.getElementById('publishNowForm');
            const brandId = form.brand_id.value;
            const content = form.content.value;
            const accountRadio = form.querySelector('input[name="social_account_id"]:checked');

            if (!brandId) {
                alert('Please select a brand.');
                return;
            }

            if (!accountRadio) {
                alert('Please select a social account to publish to.');
                return;
            }

            const socialAccountId = parseInt(accountRadio.value, 10);
            const submitBtn = document.getElementById('submitBtn');
            const alertBox = document.getElementById('publishAlert');

            submitBtn.disabled = true;
            submitBtn.textContent = 'Publishing...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const response = await fetch('/api/v1/publishing/publish-now', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        brand_id: parseInt(brandId, 10),
                        social_account_id: socialAccountId,
                        content: content
                    })
                });

                const data = await response.json();

                if (response.ok && (data.success || data.attempt || data.status === 'published' || data.status === 'success' || response.status === 200 || response.status === 201)) {
                    alertBox.style.display = 'block';
                    alertBox.style.background = 'rgba(52, 211, 153, 0.15)';
                    alertBox.style.border = '1px solid #34d399';
                    alertBox.style.color = '#34d399';
                    alertBox.innerHTML = `<strong>Success!</strong> Post dispatched successfully! <a href="{{ route('publishing.history') }}" style="color:var(--text);text-decoration:underline;margin-left:0.5rem;">View History &rarr;</a>`;
                    
                    alert('Post published successfully!');
                    form.reset();
                    document.getElementById('charCount').textContent = '0 / 5000 characters';
                    setTimeout(() => {
                        window.location.href = "{{ route('publishing.history') }}";
                    }, 1200);
                } else {
                    const errorMsg = data.message || data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to publish post.');
                    alertBox.style.display = 'block';
                    alertBox.style.background = 'rgba(248, 113, 113, 0.15)';
                    alertBox.style.border = '1px solid #f87171';
                    alertBox.style.color = '#f87171';
                    alertBox.textContent = `Publishing Error: ${errorMsg}`;

                    alert('Publishing Error: ' + errorMsg);
                }
            } catch (err) {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(248, 113, 113, 0.15)';
                alertBox.style.border = '1px solid #f87171';
                alertBox.style.color = '#f87171';
                alertBox.textContent = `Network Error: ${err.message}`;

                alert('Network Error: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Publish Now \u2192';
            }
        }
    </script>
</x-app-layout>
