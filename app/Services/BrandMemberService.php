<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\User;
use Illuminate\Support\Arr;

class BrandMemberService
{
    protected array $rolePermissions = [
        'owner' => ['read', 'write', 'approve', 'manage_members'],
        'admin' => ['read', 'write', 'approve', 'manage_members'],
        'editor' => ['read', 'write'],
        'reviewer' => ['read', 'approve'],
        'viewer' => ['read'],
    ];

    public function inviteMember(Brand $brand, User $inviter, array $data): BrandMember
    {
        $user = User::query()->where('email', $data['email'])->firstOrFail();

        if ($brand->user_id === $user->id) {
            throw new \RuntimeException('The brand owner already has full access.');
        }

        $role = $data['role'] ?? 'editor';
        $permissions = $this->rolePermissions[$role] ?? $this->rolePermissions['editor'];

        return BrandMember::query()->updateOrCreate(
            ['brand_id' => $brand->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'permissions' => $permissions,
                'status' => $data['status'] ?? 'active',
                'invited_by' => $inviter->id,
                'joined_at' => now(),
            ]
        );
    }

    public function updateMemberRole(BrandMember $member, array $data): BrandMember
    {
        $role = $data['role'] ?? $member->role;
        $member->fill([
            'role' => $role,
            'permissions' => Arr::get($this->rolePermissions, $role, $this->rolePermissions['editor']),
        ]);
        $member->save();

        return $member->fresh();
    }
}
