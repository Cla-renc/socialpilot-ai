<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Content Calendar · Phase 8</p>
                <h1>Social Media Calendar</h1>
                <p class="muted">Interactive multi-brand content calendar with real-time database sync.</p>
            </div>
            <div class="page-tools">
                @if($selectedBrand)
                    <a href="{{ route('brands.scheduling.schedule', $selectedBrand) }}" class="button">+ Schedule Post</a>
                @endif
                <a href="{{ route('scheduling.dashboard') }}" class="button-secondary">Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Filter toolbar --}}
    <div class="card panel" style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <label style="font-weight:600;font-size:0.88rem;">Brand:</label>
            <select id="brandSelect" class="input" style="width:auto;min-width:180px;" onchange="reloadCalendar()">
                @foreach($brands as $b)
                    <option value="{{ $b->id }}" {{ ($selectedBrand?->id == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>

            <label style="font-weight:600;font-size:0.88rem;">Platform:</label>
            <select id="platformSelect" class="input" style="width:auto;" onchange="reloadCalendar()">
                <option value="">All Platforms</option>
                <option value="facebook">Facebook</option>
                <option value="instagram">Instagram</option>
                <option value="tiktok">TikTok</option>
                <option value="youtube">YouTube</option>
                <option value="linkedin">LinkedIn</option>
            </select>

            <label style="font-weight:600;font-size:0.88rem;">Status:</label>
            <select id="statusSelect" class="input" style="width:auto;" onchange="reloadCalendar()">
                <option value="">All Statuses</option>
                <option value="scheduled">Scheduled</option>
                <option value="awaiting_approval">Awaiting Approval</option>
                <option value="published">Published</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div style="display:flex;gap:0.5rem;align-items:center;">
            <button type="button" class="button-secondary" onclick="changeMonth(-1)">&larr; Prev</button>
            <span id="currentMonthLabel" style="font-weight:700;font-size:1.05rem;min-width:140px;text-align:center;"></span>
            <button type="button" class="button-secondary" onclick="changeMonth(1)">Next &rarr;</button>
        </div>
    </div>

    {{-- Calendar Grid Container --}}
    <div class="card panel" style="padding:1rem;">
        <div id="calendarGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;">
            {{-- Day headers --}}
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Sun</div>
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Mon</div>
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Tue</div>
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Wed</div>
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Thu</div>
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Fri</div>
            <div style="font-weight:700;text-align:center;padding:0.5rem;font-size:0.85rem;color:var(--muted);">Sat</div>
        </div>
        <div id="calendarDays" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin-top:6px;"></div>
    </div>

    {{-- Post Details Modal --}}
    <div id="postModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
        <div class="card panel" style="width:500px;max-width:90%;background:var(--surface,#fff);box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--stroke,#e5e7eb);padding-bottom:0.75rem;">
                <h3 id="modalTitle" style="margin:0;font-size:1.15rem;">Post Details</h3>
                <button type="button" onclick="closeModal()" style="background:none;border:none;font-size:1.25rem;cursor:pointer;">&times;</button>
            </div>
            <div id="modalBody" style="margin:1rem 0;font-size:0.9rem;line-height:1.5;"></div>
            <div style="display:flex;justify-content:flex-end;gap:0.5rem;border-top:1px solid var(--stroke,#e5e7eb);padding-top:0.75rem;">
                <button type="button" class="button-secondary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentYear = new Date().getFullYear();
        let currentMonth = new Date().getMonth(); // 0-indexed
        let calendarPosts = [];

        const platformColors = {
            facebook: '#1877f2',
            instagram: '#e1306c',
            tiktok: '#010101',
            youtube: '#ff0000',
            linkedin: '#0077b5'
        };

        function updateMonthLabel() {
            const date = new Date(currentYear, currentMonth, 1);
            document.getElementById('currentMonthLabel').textContent = date.toLocaleString('default', { month: 'long', year: 'numeric' });
        }

        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            reloadCalendar();
        }

        async function reloadCalendar() {
            updateMonthLabel();
            const brandId = document.getElementById('brandSelect').value;
            if (!brandId) return;

            const startDate = new Date(currentYear, currentMonth, 1).toISOString().split('T')[0];
            const endDate = new Date(currentYear, currentMonth + 1, 0).toISOString().split('T')[0];
            const platform = document.getElementById('platformSelect').value;
            const status = document.getElementById('statusSelect').value;

            let url = `/api/v1/brands/${brandId}/calendar?start_date=${startDate}&end_date=${endDate}`;
            if (platform) url += `&platform=${encodeURIComponent(platform)}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;

            try {
                const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                calendarPosts = json.data || [];
                renderGrid();
            } catch (err) {
                console.error("Error loading calendar:", err);
            }
        }

        function renderGrid() {
            const container = document.getElementById('calendarDays');
            container.innerHTML = '';

            const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
            const totalDays = new Date(currentYear, currentMonth + 1, 0).getDate();

            // Empty cells before start of month
            for (let i = 0; i < firstDayIndex; i++) {
                const empty = document.createElement('div');
                empty.style.minHeight = '100px';
                empty.style.background = 'var(--background, #f9fafb)';
                empty.style.borderRadius = '6px';
                empty.style.opacity = '0.5';
                container.appendChild(empty);
            }

            // Days of the month
            for (let d = 1; d <= totalDays; d++) {
                const dayCell = document.createElement('div');
                dayCell.style.minHeight = '100px';
                dayCell.style.border = '1px solid var(--stroke, #e5e7eb)';
                dayCell.style.borderRadius = '6px';
                dayCell.style.padding = '0.35rem';
                dayCell.style.background = 'var(--surface, #ffffff)';
                dayCell.style.display = 'flex';
                dayCell.style.flexDirection = 'column';
                dayCell.style.gap = '0.25rem';

                const dayHeader = document.createElement('div');
                dayHeader.style.fontWeight = '700';
                dayHeader.style.fontSize = '0.85rem';
                dayHeader.style.marginBottom = '0.25rem';
                dayHeader.textContent = d;
                dayCell.appendChild(dayHeader);

                // Date string for comparison YYYY-MM-DD
                const mStr = String(currentMonth + 1).padStart(2, '0');
                const dStr = String(d).padStart(2, '0');
                const cellDateStr = `${currentYear}-${mStr}-${dStr}`;

                // Filter posts for this day
                const postsOnDay = calendarPosts.filter(p => (p.scheduled_at || '').startsWith(cellDateStr));

                postsOnDay.forEach(p => {
                    const badge = document.createElement('div');
                    const plat = (p.platforms && p.platforms.length > 0) ? p.platforms[0].platform : (p.platform || 'social');
                    const color = platformColors[plat.toLowerCase()] || '#4f46e5';

                    badge.style.background = color;
                    badge.style.color = '#fff';
                    badge.style.fontSize = '0.72rem';
                    badge.style.fontWeight = '600';
                    badge.style.padding = '0.2rem 0.4rem';
                    badge.style.borderRadius = '4px';
                    badge.style.cursor = 'pointer';
                    badge.style.whiteSpace = 'nowrap';
                    badge.style.overflow = 'hidden';
                    badge.style.textOverflow = 'ellipsis';
                    badge.textContent = `${plat.toUpperCase()}: ${p.title || 'Post'}`;

                    badge.onclick = () => openPostModal(p);
                    dayCell.appendChild(badge);
                });

                container.appendChild(dayCell);
            }
        }

        function openPostModal(p) {
            document.getElementById('modalTitle').textContent = p.title || 'Scheduled Post';
            const platList = (p.platforms || []).map(pl => pl.platform).join(', ') || p.platform || 'General';
            document.getElementById('modalBody').innerHTML = `
                <p><strong>Scheduled Time:</strong> ${p.scheduled_at} (${p.timezone || 'UTC'})</p>
                <p><strong>Status:</strong> <span style="text-transform:capitalize;">${p.status}</span></p>
                <p><strong>Platforms:</strong> ${platList}</p>
                <p><strong>Caption / Content:</strong></p>
                <div style="background:var(--background,#f9fafb);padding:0.75rem;border-radius:6px;white-space:pre-wrap;">${p.caption || p.body || p.title || 'No text content'}</div>
            `;
            document.getElementById('postModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('postModal').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            reloadCalendar();
        });
    </script>
</x-app-layout>
