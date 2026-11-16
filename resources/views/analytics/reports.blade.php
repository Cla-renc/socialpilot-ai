<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Analytics Studio · Reports</p>
                <h1>Analytics Reports</h1>
                <p class="muted">Generate and export comprehensive performance reports in PDF or CSV format.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('analytics.dashboard') }}" class="button-secondary">&larr; Back to Overview</a>
            </div>
        </div>
    </x-slot>

    {{-- Sub-navigation tabs --}}
    <div style="display:flex;gap:0.75rem;margin-bottom:1.5rem;border-bottom:1px solid var(--stroke);padding-bottom:0.75rem;flex-wrap:wrap;">
        <a href="{{ route('analytics.dashboard') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Overview</a>
        <a href="{{ route('analytics.content') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Content</a>
        <a href="{{ route('analytics.audience') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Audience</a>
        <a href="{{ route('analytics.platforms') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Platforms</a>
        <a href="{{ route('analytics.recommendations') }}" class="button-secondary" style="padding:0.45rem 1rem;font-size:0.88rem;">Recommendations</a>
        <a href="{{ route('analytics.reports') }}" class="button" style="padding:0.45rem 1rem;font-size:0.88rem;">Reports</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;margin-bottom:2rem;">
        {{-- Report Generation Form --}}
        <div class="card panel">
            <h2 style="margin-top:0;margin-bottom:1rem;font-size:1.15rem;">Generate New Report</h2>
            <form id="report-gen-form" onsubmit="handleGenerateReport(event)">
                <div style="margin-bottom:1rem;">
                    <label for="brand_id" style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">Brand</label>
                    <select name="brand_id" id="brand_id" required style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.88rem;">
                        <option value="">Select Brand</option>
                        @foreach($brands as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom:1rem;">
                    <label for="report_type" style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">Report Type</label>
                    <select name="report_type" id="report_type" required style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.88rem;">
                        <option value="summary">Summary Performance</option>
                        <option value="content">Content Breakdown</option>
                        <option value="audience">Audience Demographics</option>
                        <option value="full">Full Executive Report</option>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
                    <div>
                        <label for="period_start" style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">Start Date</label>
                        <input type="date" name="period_start" id="period_start" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.85rem;" value="{{ date('Y-m-d', strtotime('-30 days')) }}">
                    </div>
                    <div>
                        <label for="period_end" style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">End Date</label>
                        <input type="date" name="period_end" id="period_end" required style="width:100%;padding:0.45rem 0.5rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.85rem;" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label for="format" style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:0.35rem;">Export Format</label>
                    <select name="format" id="format" required style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--stroke, #d1d5db);border-radius:6px;font-size:0.88rem;">
                        <option value="pdf">PDF Document</option>
                        <option value="csv">CSV Spreadsheet</option>
                        <option value="json">JSON Data</option>
                    </select>
                </div>

                <button type="submit" id="gen-btn" class="button" style="width:100%;text-align:center;">Generate Report</button>
                <div id="gen-msg" style="margin-top:0.75rem;font-size:0.82rem;display:none;"></div>
            </form>
        </div>

        {{-- Reports Table --}}
        <div class="card panel">
            <h2 style="margin-top:0;margin-bottom:1rem;font-size:1.15rem;">Generated Reports</h2>
            @if($reports->isEmpty())
                <p class="muted" style="padding:2rem 0;text-align:center;">No reports generated yet. Use the form to trigger an export.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                        <thead>
                            <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Report Name</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Type</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Format</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Status</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;">Date</th>
                                <th style="text-align:right;padding:0.5rem 0.75rem;font-weight:600;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reports as $rep)
                                <tr style="border-bottom:1px solid var(--stroke, #e5e7eb);">
                                    <td style="padding:0.6rem 0.75rem;font-weight:500;">{{ $rep->name ?? 'Analytics Report #' . $rep->id }}</td>
                                    <td style="padding:0.6rem 0.75rem;text-transform:capitalize;">{{ $rep->report_type }}</td>
                                    <td style="padding:0.6rem 0.75rem;text-transform:uppercase;font-size:0.78rem;">{{ $rep->format ?? 'PDF' }}</td>
                                    <td style="padding:0.6rem 0.75rem;">
                                        <span style="padding:0.2rem 0.55rem;border-radius:12px;font-size:0.75rem;
                                            background:{{ $rep->status === 'completed' ? 'rgba(16,185,129,0.12)' : ($rep->status === 'failed' ? 'rgba(239,68,68,0.12)' : 'rgba(245,158,11,0.12)') }};
                                            color:{{ $rep->status === 'completed' ? '#059669' : ($rep->status === 'failed' ? '#dc2626' : '#d97706') }};text-transform:capitalize;">
                                            {{ $rep->status }}
                                        </span>
                                    </td>
                                    <td style="padding:0.6rem 0.75rem;" class="muted">{{ $rep->created_at ? $rep->created_at->diffForHumans() : '—' }}</td>
                                    <td style="padding:0.6rem 0.75rem;text-align:right;">
                                        @if($rep->status === 'completed' && $rep->file_path)
                                            <a href="{{ route('analytics.reports.download', $rep) }}" class="button-secondary" style="font-size:0.78rem;padding:0.25rem 0.6rem;">Download</a>
                                        @else
                                            <span class="muted" style="font-size:0.78rem;">{{ $rep->status === 'processing' ? 'Generating...' : 'Unavailable' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:1rem;">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
    function handleGenerateReport(e) {
        e.preventDefault();
        const form = document.getElementById('report-gen-form');
        const btn = document.getElementById('gen-btn');
        const msg = document.getElementById('gen-msg');
        
        btn.disabled = true;
        btn.textContent = 'Generating...';
        msg.style.display = 'none';

        const payload = {
            brand_id: form.brand_id.value,
            report_type: form.report_type.value,
            period_start: form.period_start.value,
            period_end: form.period_end.value,
            format: form.format.value
        };

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        fetch('/api/v1/analytics/reports', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || ''
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            msg.style.display = 'block';
            msg.style.color = '#059669';
            msg.textContent = 'Report request queued successfully! Refresh shortly to download.';
            setTimeout(() => window.location.reload(), 2000);
        })
        .catch(err => {
            msg.style.display = 'block';
            msg.style.color = '#dc2626';
            msg.textContent = 'Failed to queue report generation.';
            btn.disabled = false;
            btn.textContent = 'Generate Report';
        });
    }
    </script>
</x-app-layout>
