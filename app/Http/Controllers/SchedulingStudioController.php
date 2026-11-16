<?php

namespace App\Http\Controllers;

use App\Models\AutopilotSetting;
use App\Models\Brand;
use App\Models\PublishingQueue;
use App\Models\ScheduledPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchedulingStudioController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $brands = Brand::where('user_id', $user->id)->get();

        $brandIds = $brands->pluck('id');

        $stats = [
            'total_scheduled' => ScheduledPost::whereIn('brand_id', $brandIds)
                ->where('status', 'scheduled')->count(),
            'pending_approval' => ScheduledPost::whereIn('brand_id', $brandIds)
                ->whereIn('status', ['pending_approval', 'awaiting_approval'])->count(),
            'published_this_week' => ScheduledPost::whereIn('brand_id', $brandIds)
                ->where('status', 'published')
                ->whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'active_queues' => PublishingQueue::whereIn('brand_id', $brandIds)
                ->where('status', 'active')->count(),
            'autopilot_enabled' => AutopilotSetting::whereIn('brand_id', $brandIds)
                ->where('is_enabled', true)->count(),
        ];

        $upcoming = ScheduledPost::whereIn('brand_id', $brandIds)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->with(['brand', 'platforms'])
            ->get();

        return view('scheduling.dashboard', compact('brands', 'stats', 'upcoming'));
    }

    public function calendar(Request $request)
    {
        $user = Auth::user();
        $brands = Brand::where('user_id', $user->id)->get();
        $selectedBrand = $request->brand_id ? Brand::where('user_id', $user->id)->find($request->brand_id) : $brands->first();

        return view('scheduling.calendar', compact('brands', 'selectedBrand'));
    }

    public function schedulePost(Brand $brand)
    {
        $this->authorizeOwner($brand);
        $brand->load('socialAccounts');
        $drafts = $brand->contentDrafts()
            ->whereIn('status', ['draft', 'approved', 'ready', 'scheduled'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('scheduling.schedule-post', compact('brand', 'drafts'));
    }

    public function bestTimes(Brand $brand)
    {
        $this->authorizeOwner($brand);
        return view('scheduling.best-times', compact('brand'));
    }

    public function queue(Brand $brand)
    {
        $this->authorizeOwner($brand);
        $queues = $brand->publishingQueues()->with(['items.scheduledPost'])->get();
        return view('scheduling.queue', compact('brand', 'queues'));
    }

    public function autopilot(Brand $brand)
    {
        $this->authorizeOwner($brand);
        $autopilot = AutopilotSetting::firstOrNew(['brand_id' => $brand->id]);
        $rules = $brand->autopilotRules()->orderBy('priority')->get();
        return view('scheduling.autopilot', compact('brand', 'autopilot', 'rules'));
    }

    public function activity(Brand $brand)
    {
        $this->authorizeOwner($brand);
        $logs = $brand->autopilotActivityLogs()->orderByDesc('created_at')->paginate(25);
        return view('scheduling.activity', compact('brand', 'logs'));
    }

    protected function authorizeOwner(Brand $brand): void
    {
        if ($brand->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to brand.');
        }
    }
}
