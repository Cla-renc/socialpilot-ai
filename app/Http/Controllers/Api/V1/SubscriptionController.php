<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $plans = Plan::query()
            ->where('status', 'active')
            ->orderBy('price_monthly')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'price_monthly',
                'brand_limit',
                'social_account_limit',
                'ai_generation_limit',
            ]);

        $subscription = $request->user()
            ->subscriptions()
            ->with('plan')
            ->latest()
            ->first();

        return response()->json([
            'plans' => $plans,
            'active_subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'renews_at' => $subscription->renews_at?->toISOString(),
                'plan' => [
                    'id' => $subscription->plan?->id,
                    'name' => $subscription->plan?->name,
                    'slug' => $subscription->plan?->slug,
                    'price_monthly' => $subscription->plan?->price_monthly,
                ],
            ] : null,
        ]);
    }
}
