<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\SocialAccount;
use App\Policies\BrandMemberPolicy;
use App\Policies\BrandPolicy;
use App\Policies\SocialAccountPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Brand::class => BrandPolicy::class,
        BrandMember::class => BrandMemberPolicy::class,
        SocialAccount::class => SocialAccountPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
