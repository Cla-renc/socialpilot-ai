<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Jobs\PurgeUserMediaStorageJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Protected production user circuit breaker
        abort_if(
            in_array($user->id, PurgeUserMediaStorageJob::PROTECTED_USER_IDS, true),
            403,
            'Protected production user account cannot be deleted.'
        );

        // Collect owned brand media directories and logos prior to database cascade
        $ownedBrands = $user->brands()->get();
        $brandIds = $ownedBrands
            ->reject(fn ($brand) => in_array($brand->id, PurgeUserMediaStorageJob::PROTECTED_BRAND_IDS, true))
            ->pluck('id')
            ->all();

        $logoPaths = $ownedBrands
            ->whereNotNull('logo_path')
            ->pluck('logo_path')
            ->all();

        Auth::logout();

        // Transactional account deletion: queue dispatch and SQL cascade occur atomically
        DB::transaction(function () use ($user, $brandIds, $logoPaths) {
            if (! empty($brandIds) || ! empty($logoPaths)) {
                PurgeUserMediaStorageJob::dispatch($brandIds, $logoPaths, $user->id);
            }

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
