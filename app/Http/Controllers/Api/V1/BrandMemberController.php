<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandMemberRequest;
use App\Http\Requests\UpdateBrandMemberRequest;
use App\Models\Brand;
use App\Models\BrandMember;
use App\Services\BrandMemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandMemberController extends Controller
{
    public function __construct(protected BrandMemberService $memberService) {}

    public function index(Request $request, Brand $brand)
    {
        Gate::authorize('view', $brand);

        $members = $brand->members()->with('user')->latest()->get();

        return response()->json([
            'data' => $members->map(fn (BrandMember $member) => [
                'id' => $member->id,
                'brand_id' => $member->brand_id,
                'user' => [
                    'id' => $member->user?->id,
                    'name' => $member->user?->name,
                    'email' => $member->user?->email,
                ],
                'role' => $member->role,
                'permissions' => $member->permissions,
                'status' => $member->status,
                'joined_at' => $member->joined_at?->toISOString(),
            ]),
        ]);
    }

    public function store(StoreBrandMemberRequest $request, Brand $brand)
    {
        Gate::authorize('manageMembers', $brand);

        $member = $this->memberService->inviteMember($brand, $request->user(), $request->validated());

        return response()->json([
            'data' => [
                'id' => $member->id,
                'brand_id' => $member->brand_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'permissions' => $member->permissions,
                'status' => $member->status,
            ],
        ], 201);
    }

    public function update(UpdateBrandMemberRequest $request, Brand $brand, BrandMember $member)
    {
        Gate::authorize('update', $member);

        $member = $this->memberService->updateMemberRole($member, $request->validated());

        return response()->json([
            'data' => [
                'id' => $member->id,
                'brand_id' => $member->brand_id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'permissions' => $member->permissions,
            ],
        ]);
    }

    public function destroy(Request $request, Brand $brand, BrandMember $member)
    {
        Gate::authorize('delete', $member);

        $member->delete();

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }
}
