<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy
{
    public function view(User $user, Brand $brand): bool
    {
        return $brand->user_id === $user->id || $brand->members()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Brand $brand): bool
    {
        return $brand->user_id === $user->id;
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $brand->user_id === $user->id;
    }

    public function manageMembers(User $user, Brand $brand): bool
    {
        return $brand->user_id === $user->id || $brand->members()->where('user_id', $user->id)->whereIn('role', ['admin', 'owner'])->exists();
    }
}
