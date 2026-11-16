<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Content Studio &rarr; Scheduler · Phase 8</p>
                <h1>Schedule Content for {{ $brand->name }}</h1>
                <p class="muted">Schedule existing content drafts or create platform-adapted scheduled posts.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('scheduling.calendar') }}" class="button-secondary">Calendar</a>
                <a href="{{ route('brands.scheduling.best-times', $brand) }}" class="button-secondary">Best Times</a>
            </div>
        </div>
    </x-slot>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        {{-- Schedule Form --}}
        <div class="card panel">
            <h2 style="margin-top:0;font-size:1.15rem;margin-bottom:1.25rem;">New Scheduled Post</h2>

            <div id="alertBox" style="display:none;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;font-size:0.9rem;"></div>

            <form id="scheduleForm" onsubmit="handleScheduleSubmit(event)">
                @csrf
                <div style="display:flex;flex-direction:column;gap:1.25rem;">
                    {{-- Content Draft Selector --}}
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Select Content Draft (from Phase 6 Content Library)</label>
                        <select id="draftSelect" class="input" style="width:100%;" onchange="onDraftChange()">
                            <option value="">-- Choose an existing draft or enter manual content --</option>
                            @foreach($drafts as $d)
                                <option value="{{ $d->id }}" data-title="{{ e($d->title) }}" data-body="{{ e($d->body ?: $d->content) }}" data-caption="{{ e($d->caption) }}">
                                    #{{ $d->id }} - {{ $d->title }} ({{ ucfirst($d->status) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Post Title --}}
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Post Title / Summary *</label>
                        <input type="text" id="postTitle" class="input" style="width:100%;" required placeholder="e.g. Q4 Growth Sprint Announcement">
                    </div>

                    {{-- Post Caption / Content --}}
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Caption & Text Body *</label>
                        <textarea id="postCaption" class="input" style="width:100%;min-height:120px;" required placeholder="Write or paste your post copy here..."></textarea>
                    </div>

                    {{-- Target Platforms --}}
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Target Social Platform *</label>
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                            @foreach(['instagram', 'facebook', 'tiktok', 'youtube', 'linkedin'] as $plat)
                                <label style="display:flex;align-items:center;gap:0.35rem;cursor:pointer;font-size:0.9rem;">
                                    <input type="radio" name="platform" value="{{ $plat }}" {{ $loop->first ? 'checked' : '' }} onchange="onPlatformSelect('{{ $plat }}')">
                                    <span style="text-transform:capitalize;">{{ $plat }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Connected Social Account (Optional) --}}
                    <div>
                        <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Connected Social Account</label>
                        <select id="socialAccountSelect" class="input" style="width:100%;">
                            <option value="">-- Post to General Platform Queue (No specific account bound) --</option>
                            @foreach($brand->socialAccounts as $acc)
                                <option value="{{ $acc->id }}" data-platform="{{ strtolower($acc->provider ?? $acc->platform) }}">
                                    {{ ucfirst($acc->provider ?? $acc->platform) }}: {{ $acc->account_name ?: '@'.$acc->account_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date & Time Selection --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                        <div>
                            <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Scheduled Date *</label>
                            <input type="date" id="scheduledDate" class="input" style="width:100%;" required min="{{ date('Y-m-d') }}">
                        </div>
                        <div>
                            <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Scheduled Time *</label>
                            <input type="time" id="scheduledTime" class="input" style="width:100%;" required value="10:00">
                        </div>
                        <div>
                            <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Timezone *</label>
                            <input type="text" id="postTimezone" class="input" style="width:100%;" value="{{ $brand->settings?->timezone ?: 'UTC' }}">
                        </div>
                    </div>

                    {{-- Approval and Options --}}
                    <div style="display:flex;gap:1.5rem;align-items:center;margin-top:0.25rem;">
                        <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.9rem;">
                            <input type="checkbox" id="requiresApproval" value="1">
                            <span>Require manager approval before publishing</span>
                        </label>
                    </div>

                    <div style="margin-top:0.75rem;display:flex;gap:0.75rem;">
                        <button type="submit" id="submitBtn" class="button">Confirm & Schedule</button>
                        <a href="{{ route('scheduling.calendar') }}" class="button-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>

        {{-- AI Best-Times Sidebar Panel --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">AI Recommended Times</h3>
                <p class="muted" style="font-size:0.82rem;margin-top:0.25rem;">
                    Platform heuristics and audience patterns for <strong>{{ $brand->name }}</strong>:
                </p>

                <div id="recommendationsList" style="margin-top:1rem;display:flex;flex-direction:column;gap:0.5rem;">
                    <div class="muted" style="font-size:0.85rem;">Loading optimal times...</div>
                </div>
            </div>

            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">Autopilot Notice</h3>
                <p class="muted" style="font-size:0.85rem;line-height:1.4;">
                    Posts scheduled here are added directly to the brand's verified publishing records. Phase 8 schedules and organizes content. Publishing to social APIs occurs in Phase 9.
                </p>
            </div>
        </div>
    </div>

    <script>
        const brandId = {{ $brand->id }};

        function onDraftChange() {
            const select = document.getElementById('draftSelect');
            const selectedOpt = select.options[select.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) return;

            const title = selectedOpt.getAttribute('data-title');
            const body = selectedOpt.getAttribute('data-body');
            const caption = selectedOpt.getAttribute('data-caption');

            if (title) document.getElementById('postTitle').value = title;
            if (caption || body) document.getElementById('postCaption').value = caption || body;
        }

        async function fetchBestTimes(platform) {
            const container = document.getElementById('recommendationsList');
            container.innerHTML = '<div class="muted" style="font-size:0.85rem;">Analyzing audience patterns...</div>';

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/scheduling/best-times?platform=${platform}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                const recs = json.data || [];

                if (recs.length === 0) {
                    container.innerHTML = '<div class="muted" style="font-size:0.85rem;">No recommendations generated yet.</div>';
                    return;
                }

                container.innerHTML = '';
                recs.slice(0, 4).forEach(r => {
                    const item = document.createElement('div');
                    item.style.padding = '0.5rem';
                    item.style.border = '1px solid var(--stroke, #e5e7eb)';
                    item.style.borderRadius = '6px';
                    item.style.cursor = 'pointer';
                    item.style.transition = 'all 0.15s ease';

                    item.innerHTML = `
                        <div style="font-weight:600;font-size:0.88rem;color:var(--primary, #4f46e5);">${r.day_of_week} at ${r.suggested_time}</div>
                        <div class="muted" style="font-size:0.78rem;margin-top:0.2rem;">${r.reason}</div>
                    `;

                    item.onclick = () => {
                        document.getElementById('scheduledTime').value = r.suggested_time;
                    };

                    container.appendChild(item);
                });
            } catch (e) {
                container.innerHTML = '<div class="muted" style="font-size:0.85rem;">Using default heuristic times.</div>';
            }
        }

        function onPlatformSelect(platform) {
            fetchBestTimes(platform);
        }

        async function handleScheduleSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const alertBox = document.getElementById('alertBox');
            btn.disabled = true;
            btn.textContent = 'Scheduling...';
            alertBox.style.display = 'none';

            const platform = document.querySelector('input[name="platform"]:checked').value;
            const draftId = document.getElementById('draftSelect').value || null;
            const accountId = document.getElementById('socialAccountSelect').value || null;
            const date = document.getElementById('scheduledDate').value;
            const time = document.getElementById('scheduledTime').value;
            const tz = document.getElementById('postTimezone').value || 'UTC';

            const payload = {
                content_draft_id: draftId ? parseInt(draftId) : null,
                title: document.getElementById('postTitle').value,
                caption: document.getElementById('postCaption').value,
                platform: platform,
                social_account_id: accountId ? parseInt(accountId) : null,
                scheduled_at: `${date} ${time}:00`,
                timezone: tz,
                requires_approval: document.getElementById('requiresApproval').checked
            };

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/scheduled-posts`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok) {
                    alertBox.style.display = 'block';
                    alertBox.style.background = 'rgba(16, 185, 129, 0.12)';
                    alertBox.style.color = '#065f46';
                    alertBox.style.border = '1px solid #10b981';
                    alertBox.innerHTML = `<strong>Success!</strong> Post has been scheduled. <a href="{{ route('scheduling.calendar') }}">View on calendar &rarr;</a>`;
                    document.getElementById('scheduleForm').reset();
                } else {
                    alertBox.style.display = 'block';
                    alertBox.style.background = 'rgba(239, 68, 68, 0.12)';
                    alertBox.style.color = '#991b1b';
                    alertBox.style.border = '1px solid #ef4444';
                    alertBox.textContent = data.message || 'Error scheduling post. Please check required fields.';
                }
            } catch (err) {
                alertBox.style.display = 'block';
                alertBox.style.background = 'rgba(239, 68, 68, 0.12)';
                alertBox.style.color = '#991b1b';
                alertBox.textContent = 'Server or connection error: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.textContent = 'Confirm & Schedule';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Default tomorrow's date
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('scheduledDate').value = tomorrow.toISOString().split('T')[0];

            fetchBestTimes('instagram');
        });
    </script>
</x-app-layout>
