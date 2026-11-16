<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Social\ProviderException;
use App\Services\Social\SocialOAuthService;
use App\Services\SocialProviderCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialConnectionController extends Controller
{
    public function providers(): JsonResponse
    {
        return response()->json([
            'data' => $this->providerStatuses(),
        ]);
    }

    public function providerStatuses(): array
    {
        return app(SocialProviderCatalog::class)->statuses();
    }

    public function beginAuthorization(Request $request, Brand $brand, string $provider)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        try {
            $flow = app(SocialOAuthService::class)->begin($request->user(), $brand, $provider, 'mobile');
        } catch (ProviderException $exception) {
            return response()->json([
                'error' => [
                    'code' => 'configuration_required',
                    'message' => $exception->getMessage(),
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'authorization_url' => $flow['authorization_url'],
                'callback_scheme' => 'socialpilot://oauth/callback',
            ],
        ]);
    }

    public function connectProvider(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:facebook,instagram,tiktok,youtube'],
        ]);

        return $this->beginAuthorization($request, $brand, $validated['provider']);
    }

    public function connect(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        return response()->json([
            'error' => [
                'code' => 'oauth_required',
                'message' => 'Social accounts can only be connected through a verified provider OAuth flow. Tokens submitted by clients are not accepted.',
            ],
        ], 409);
    }
}
