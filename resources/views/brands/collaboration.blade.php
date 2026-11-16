<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Collaboration</p>
                <h1>{{ $brand->name }} team</h1>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Back to brand</a>
            </div>
        </div>
    </x-slot>

    <section class="content-grid">
        <article class="card panel">
            <h2>Members</h2>
            @if($members->isEmpty())
                <p class="muted">No collaborators invited yet.</p>
            @else
                <div class="list">
                    @foreach($members as $member)
                        <div class="line-item">
                            <div>
                                <strong>{{ $member->user?->name ?? 'Unknown user' }}</strong>
                                <div class="muted">{{ $member->user?->email ?? 'No email available' }}</div>
                            </div>
                            <span class="badge badge--{{ $member->role === 'owner' ? 'active' : 'pending' }}">{{ ucfirst($member->role) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="card panel">
            <h2>Approval queue</h2>
            @if($approvalRequests->isEmpty())
                <p class="muted">No approval requests yet.</p>
            @else
                <div class="list">
                    @foreach($approvalRequests as $request)
                        <div class="line-item">
                            <div>
                                <strong>{{ ucfirst($request->resource_type) }} #{{ $request->resource_id }}</strong>
                                <div class="muted">{{ $request->notes ?: 'No notes provided' }}</div>
                            </div>
                            <span class="badge badge--{{ $request->status === 'approved' ? 'active' : ($request->status === 'rejected' ? 'warning' : 'pending') }}">{{ ucfirst($request->status) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
</x-app-layout>
