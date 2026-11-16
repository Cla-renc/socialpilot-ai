<?php

namespace App\Policies;

use App\Models\BrandMember;
use App\Models\User;

class BrandMemberPolicy
{
    public function view(User $user, BrandMember $member): bool
    {
        return $member->brand->user_id === $user->id || $member->user_id === $user->id;
    }

    public function update(User $user, BrandMember $member): bool
    {
        return $member->brand->user_id === $user->id || $member->brand->members()->where('user_id', $user->id)->whereIn('role', ['admin', 'owner'])->exists();
    }

    public function delete(User $user, BrandMember $member): bool
    {
        return $member->brand->user_id === $user->id;
    }
}
