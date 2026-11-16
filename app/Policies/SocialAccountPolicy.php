<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;

class SocialAccountPolicy
{
    public function view(User $user, SocialAccount $socialAccount): bool
    {
        return $this->owns($user, $socialAccount);
    }

    public function update(User $user, SocialAccount $socialAccount): bool
    {
        return $this->owns($user, $socialAccount);
    }

    public function delete(User $user, SocialAccount $socialAccount): bool
    {
        return $this->owns($user, $socialAccount);
    }

    private function owns(User $user, SocialAccount $socialAccount): bool
    {
        return $socialAccount->user_id === $user->id
            && ($socialAccount->brand_id === null || $socialAccount->brand?->user_id === $user->id);
    }
}
