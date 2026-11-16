<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Autopilot Agent · Phase 8</p>
                <h1>Autopilot Studio for {{ $brand->name }}</h1>
                <p class="muted">Automate scheduling workflows with brand-specific heuristics, rules, and AI recommendations.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.scheduling.activity', $brand) }}" class="button-secondary">Activity Log</a>
                <a href="{{ route('scheduling.calendar') }}" class="button-secondary">Calendar</a>
            </div>
        </div>
    </x-slot>

    <div id="alertBox" style="display:none;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;font-size:0.9rem;"></div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
        {{-- Autopilot Configuration Panel --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            {{-- Status Card --}}
            <div class="card panel">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                    <div>
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <h2 style="margin:0;font-size:1.25rem;">Autopilot Status</h2>
                            <span class="badge" style="padding:0.25rem 0.75rem;border-radius:12px;font-size:0.8rem;font-weight:700;background:{{ $autopilot->is_enabled ? 'rgba(16,185,129,0.12)' : 'rgba(107,114,128,0.12)' }};color:{{ $autopilot->is_enabled ? '#059669' : '#4b5563' }};">
                                {{ $autopilot->is_enabled ? 'ACTIVE' : 'DISABLED' }}
                            </span>
                        </div>
                        <p class="muted" style="margin:0.25rem 0 0;font-size:0.85rem;">
                            Current operating mode: <strong>{{ strtoupper(str_replace('_', ' ', $autopilot->mode ?? 'ai_assisted')) }}</strong>
                        </p>
                    </div>

                    <div style="display:flex;gap:0.5rem;">
                        @if($autopilot->is_enabled)
                            <button type="button" class="button-secondary" onclick="toggleAutopilot('pause')">Pause</button>
                            <button type="button" class="button" style="background:#dc2626;border-color:#dc2626;" onclick="toggleAutopilot('disable')">Disable</button>
                        @else
                            <button type="button" class="button" onclick="toggleAutopilot('enable')">Enable Autopilot</button>
                        @endif
                        <button type="button" class="button-secondary" onclick="triggerCycle()">Run Cycle Now</button>
                    </div>
                </div>
            </div>

            {{-- Settings Form --}}
            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.1rem;margin-bottom:1.25rem;">Autopilot Strategy & Rules</h3>

                <form id="autopilotSettingsForm" onsubmit="saveAutopilotSettings(event)">
                    @csrf
                    <div style="display:flex;flex-direction:column;gap:1.25rem;">
                        {{-- Autopilot Mode Selector --}}
                        <div>
                            <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Autopilot Operating Mode</label>
                            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.5rem;border:1px solid var(--stroke,#e5e7eb);border-radius:6px;cursor:pointer;">
                                    <input type="radio" name="mode" value="manual" {{ ($autopilot->mode === 'manual') ? 'checked' : '' }} style="margin-top:3px;">
                                    <div>
                                        <strong>Mode A: Manual Scheduling</strong>
                                        <div class="muted" style="font-size:0.8rem;">User controls all decisions; autopilot assists with diagnostics and conflict alerts.</div>
                                    </div>
                                </label>
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.5rem;border:1px solid var(--stroke,#e5e7eb);border-radius:6px;cursor:pointer;">
                                    <input type="radio" name="mode" value="ai_assisted" {{ ($autopilot->mode === 'ai_assisted' || !$autopilot->mode) ? 'checked' : '' }} style="margin-top:3px;">
                                    <div>
                                        <strong>Mode B: AI-Assisted Scheduling (Recommended)</strong>
                                        <div class="muted" style="font-size:0.8rem;">AI generates optimal schedule proposals; user reviews and approves before persistence.</div>
                                    </div>
                                </label>
                                <label style="display:flex;align-items:flex-start;gap:0.5rem;padding:0.5rem;border:1px solid var(--stroke,#e5e7eb);border-radius:6px;cursor:pointer;">
                                    <input type="radio" name="mode" value="automatic" {{ ($autopilot->mode === 'automatic') ? 'checked' : '' }} style="margin-top:3px;">
                                    <div>
                                        <strong>Mode C: Autonomous Autopilot</strong>
                                        <div class="muted" style="font-size:0.8rem;">Autopilot schedules approved drafts into available calendar slots according to brand rules.</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Cadence Settings --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div>
                                <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Weekly Post Target</label>
                                <input type="number" id="postsPerWeek" class="input" style="width:100%;" min="1" max="21" value="{{ $autopilot->posts_per_week ?? 3 }}">
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Min Gap Between Posts (Hours)</label>
                                <input type="number" id="minGapHours" class="input" style="width:100%;" min="1" max="72" value="{{ $autopilot->min_gap_hours ?? 12 }}">
                            </div>
                        </div>

                        {{-- Preferred Publishing Days --}}
                        <div>
                            <label style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:0.35rem;">Preferred Publishing Days</label>
                            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                                @php $prefDays = $autopilot->preferred_days ?: ['monday', 'wednesday', 'friday']; @endphp
                                @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                    <label style="display:flex;align-items:center;gap:0.3rem;cursor:pointer;font-size:0.88rem;">
                                        <input type="checkbox" name="pref_days[]" value="{{ $day }}" {{ in_array($day, $prefDays) ? 'checked' : '' }}>
                                        <span style="text-transform:capitalize;">{{ substr($day, 0, 3) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Toggles --}}
                        <div style="display:flex;flex-direction:column;gap:0.5rem;">
                            <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.88rem;">
                                <input type="checkbox" id="requiresApproval" value="1" {{ ($autopilot->requires_approval ?? true) ? 'checked' : '' }}>
                                <span>Always require human approval before scheduling content into calendar</span>
                            </label>
                        </div>

                        <div>
                            <button type="submit" id="saveBtn" class="button">Save Autopilot Configuration</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Autopilot Rules & Info Sidebar --}}
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
            {{-- Rules List --}}
            <div class="card panel">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <h3 style="margin:0;font-size:1.05rem;">Active Rules ({{ $rules->count() }})</h3>
                    <button type="button" class="button-secondary" style="font-size:0.75rem;padding:0.25rem 0.5rem;" onclick="openNewRuleModal()">+ Add Rule</button>
                </div>

                @if($rules->isEmpty())
                    <p class="muted" style="font-size:0.85rem;padding:1rem 0;text-align:center;">No custom rules set. Brand default heuristics apply.</p>
                @else
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach($rules as $rule)
                            <div style="padding:0.6rem;border:1px solid var(--stroke, #e5e7eb);border-radius:6px;display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <div style="font-weight:600;font-size:0.85rem;">{{ $rule->name }}</div>
                                    <div class="muted" style="font-size:0.75rem;">Type: {{ $rule->rule_type }} · Priority: {{ $rule->priority }}</div>
                                </div>
                                <span class="badge" style="font-size:0.72rem;padding:0.2rem 0.4rem;border-radius:8px;background:{{ $rule->is_active ? 'rgba(16,185,129,0.12)' : 'rgba(107,114,128,0.12)' }};color:{{ $rule->is_active ? '#059669' : '#4b5563' }};">
                                    {{ $rule->is_active ? 'Active' : 'Off' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Safe Autopilot Guarantees --}}
            <div class="card panel">
                <h3 style="margin-top:0;font-size:1.05rem;">Autopilot Guarantees</h3>
                <ul class="muted" style="font-size:0.82rem;padding-left:1.25rem;margin:0.5rem 0 0;line-height:1.5;">
                    <li>Never claims content has been published.</li>
                    <li>Schedules into verified database calendar records only.</li>
                    <li>Checks conflicts & blackout windows automatically.</li>
                    <li>Full audit trail logged in Activity Log.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        const brandId = {{ $brand->id }};

        function showMessage(msg, isError = false) {
            const box = document.getElementById('alertBox');
            box.style.display = 'block';
            box.style.background = isError ? 'rgba(239, 68, 68, 0.12)' : 'rgba(16, 185, 129, 0.12)';
            box.style.color = isError ? '#991b1b' : '#065f46';
            box.textContent = msg;
        }

        async function toggleAutopilot(action) {
            try {
                const res = await fetch(`/api/v1/brands/${brandId}/autopilot/${action}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (res.ok) {
                    location.reload();
                } else {
                    const d = await res.json();
                    showMessage(d.message || `Failed to ${action} autopilot`, true);
                }
            } catch (err) {
                showMessage(err.message, true);
            }
        }

        async function triggerCycle() {
            try {
                const res = await fetch(`/api/v1/brands/${brandId}/autopilot/trigger`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await res.json();
                if (res.ok) {
                    showMessage(`Autopilot cycle completed! Processed: ${data.result?.scheduled_count ?? 0} post(s).`);
                } else {
                    showMessage(data.message || 'Execution error', true);
                }
            } catch (err) {
                showMessage(err.message, true);
            }
        }

        async function saveAutopilotSettings(e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const selectedMode = document.querySelector('input[name="mode"]:checked').value;
            const daysChecked = Array.from(document.querySelectorAll('input[name="pref_days[]"]:checked')).map(el => el.value);

            const payload = {
                mode: selectedMode,
                posts_per_week: parseInt(document.getElementById('postsPerWeek').value),
                min_gap_hours: parseInt(document.getElementById('minGapHours').value),
                preferred_days: daysChecked,
                requires_approval: document.getElementById('requiresApproval').checked
            };

            try {
                const res = await fetch(`/api/v1/brands/${brandId}/autopilot`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    showMessage('Autopilot configuration updated successfully.');
                } else {
                    const d = await res.json();
                    showMessage(d.message || 'Error saving settings', true);
                }
            } catch (err) {
                showMessage(err.message, true);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Autopilot Configuration';
            }
        }

        function openNewRuleModal() {
            const ruleName = prompt('Enter a name for the new Autopilot Rule:');
            if (!ruleName) return;

            fetch(`/api/v1/brands/${brandId}/autopilot/rules`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    name: ruleName,
                    rule_type: 'content_category',
                    priority: 10,
                    is_active: true
                })
            }).then(r => {
                if (r.ok) location.reload();
                else alert('Could not create rule');
            });
        }
    </script>
</x-app-layout>
