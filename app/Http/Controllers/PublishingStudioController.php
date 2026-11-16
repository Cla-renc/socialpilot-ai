<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\PublishingAttempt;
use App\Models\PublishingNotification;
use Illuminate\Http\Request;

class PublishingStudioController extends Controller
{
    public function middleware($middleware)
    {
        return $this;
    }

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function dashboard(Request $request)
    {
        $user            = $request->user();
        $brands          = Brand::where('user_id', $user->id)->get();
        $selectedBrandId = $request->brand_id ?? optional($brands->first())->id;

        $stats = [
            'total'      => PublishingAttempt::where('user_id', $user->id)->count(),
            'published'  => PublishingAttempt::where('user_id', $user->id)->where('status', 'published')->count(),
            'failed'     => PublishingAttempt::where('user_id', $user->id)->where('status', 'failed')->count(),
            'processing' => PublishingAttempt::where('user_id', $user->id)->whereIn('status', ['processing', 'publishing'])->count(),
        ];

        $recentAttempts = PublishingAttempt::where('user_id', $user->id)
            ->when($selectedBrandId, fn($q) => $q->where('brand_id', $selectedBrandId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('publishing.dashboard', compact('brands', 'stats', 'recentAttempts', 'selectedBrandId'));
    }

    public function history(Request $request)
    {
        $user    = $request->user();
        $brands  = Brand::where('user_id', $user->id)->get();
        $attempts = PublishingAttempt::where('user_id', $user->id)
            ->when($request->brand_id,  fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->platform,  fn($q) => $q->where('platform', $request->platform))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('publishing.history', compact('brands', 'attempts'));
    }

    public function failed(Request $request)
    {
        $user    = $request->user();
        $brands  = Brand::where('user_id', $user->id)->get();
        $attempts = PublishingAttempt::where('user_id', $user->id)
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('publishing.failed', compact('brands', 'attempts'));
    }

    public function show(PublishingAttempt $attempt, Request $request)
    {
        if ($attempt->user_id !== $request->user()->id) {
            abort(403);
        }
        return view('publishing.show', compact('attempt'));
    }

    public function publishNow(Request $request)
    {
        $user   = $request->user();
        $brands = Brand::where('user_id', $user->id)->with('socialAccounts')->get();
        return view('publishing.publish-now', compact('brands'));
    }

    public function notifications(Request $request)
    {
        $user          = $request->user();
        $notifications = PublishingNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);
        return view('publishing.notifications', compact('notifications'));
    }
}
