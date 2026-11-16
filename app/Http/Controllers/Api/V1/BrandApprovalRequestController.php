<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandApprovalRequest;
use App\Models\Brand;
use App\Models\BrandApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandApprovalRequestController extends Controller
{
    public function index(Request $request, Brand $brand)
    {
        Gate::authorize('view', $brand);

        $requests = $brand->approvalRequests()->with(['requester', 'approver'])->latest()->get();

        return response()->json([
            'data' => $requests->map(fn (BrandApprovalRequest $requestItem) => [
                'id' => $requestItem->id,
                'brand_id' => $requestItem->brand_id,
                'resource_type' => $requestItem->resource_type,
                'resource_id' => $requestItem->resource_id,
                'status' => $requestItem->status,
                'notes' => $requestItem->notes,
                'requested_by' => [
                    'id' => $requestItem->requester?->id,
                    'name' => $requestItem->requester?->name,
                ],
                'approved_by' => [
                    'id' => $requestItem->approver?->id,
                    'name' => $requestItem->approver?->name,
                ],
                'metadata' => $requestItem->metadata,
            ]),
        ]);
    }

    public function store(StoreBrandApprovalRequest $request, Brand $brand)
    {
        Gate::authorize('view', $brand);

        $approval = $brand->approvalRequests()->create([
            'requested_by' => $request->user()->id,
            'resource_type' => $request->validated()['resource_type'],
            'resource_id' => $request->validated()['resource_id'],
            'status' => $request->validated()['status'] ?? 'pending',
            'notes' => $request->validated()['notes'] ?? null,
            'metadata' => $request->validated()['metadata'] ?? [],
        ]);

        return response()->json([
            'data' => [
                'id' => $approval->id,
                'brand_id' => $approval->brand_id,
                'resource_type' => $approval->resource_type,
                'resource_id' => $approval->resource_id,
                'status' => $approval->status,
                'notes' => $approval->notes,
            ],
        ], 201);
    }

    public function update(Request $request, Brand $brand, BrandApprovalRequest $approvalRequest)
    {
        abort_unless($brand->id === $approvalRequest->brand_id, 403, 'Approval request does not belong to this brand.');
        Gate::authorize('view', $brand);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $approvalRequest->fill([
            'status' => $validated['status'],
            'approved_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? $approvalRequest->notes,
        ]);
        $approvalRequest->save();

        return response()->json([
            'data' => [
                'id' => $approvalRequest->id,
                'status' => $approvalRequest->status,
                'approved_by' => $approvalRequest->approved_by,
            ],
        ]);
    }
}
