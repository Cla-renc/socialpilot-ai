<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\Social\InvalidOAuthStateException;
use App\Services\Social\ProviderException;
use App\Services\Social\SocialOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialOAuthController extends Controller
{
    public function connect(Request $request, Brand $brand, string $provider, SocialOAuthService $oauth): RedirectResponse
    {
        try {
            $flow = $oauth->begin($request->user(), $brand, $provider, 'web');

            return redirect()->away($flow['authorization_url']);
        } catch (ProviderException $exception) {
            return redirect()->route('brands.social-accounts.index', $brand)
                ->with('error', $exception->getMessage());
        }
    }

    public function callback(Request $request, string $provider, SocialOAuthService $oauth)
    {
        try {
            $result = $oauth->complete(
                $provider,
                $request->query('state'),
                $request->query('code'),
                $request->query('error')
            );
        } catch (InvalidOAuthStateException) {
            abort(400, 'The OAuth state is invalid or expired.');
        }

        if (($result['mode'] ?? 'web') === 'mobile') {
            return redirect()->away('socialpilot://oauth/callback?'.http_build_query([
                'status' => $result['status'],
                'brand_id' => $result['brand_id'],
                'message' => $result['status'] === 'connected'
                    ? 'Social account connected.'
                    : $result['message'],
            ], '', '&', PHP_QUERY_RFC3986));
        }

        $redirect = redirect()->route('brands.social-accounts.index', $result['brand_id']);
        if ($result['status'] === 'connected') {
            return $redirect->with('success', 'Connected: '.implode(', ', $result['account_names']).'.');
        }

        return $redirect->with('error', $result['message']);
    }
}
