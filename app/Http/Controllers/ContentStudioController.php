<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\TrendingTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentStudioController extends Controller
{
    /**
     * Content library — list all drafts for the given brand.
     */
    public function index(Request $request, Brand $brand)
    {
        $this->authoriseBrand($brand);

        $query = BrandContentDraft::where('brand_id', $brand->id)
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }
        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($objective = $request->input('objective')) {
            $query->where('objective', $objective);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $drafts = $query->paginate(15)->withQueryString();
        $brands = Auth::user()->brands()->get();

        return view('content.index', compact('brand', 'brands', 'drafts'));
    }

    /**
     * Content Studio — generation form.
     */
    public function studio(Request $request, Brand $brand)
    {
        $this->authoriseBrand($brand);

        // Load recent trends for the selector
        $trends = TrendingTopic::where('brand_id', $brand->id)
            ->orderByDesc('relevance_score')
            ->limit(50)
            ->get(['id', 'title', 'relevance_score', 'momentum', 'category']);

        $brands = Auth::user()->brands()->get();

        return view('content.studio', compact('brand', 'brands', 'trends'));
    }

    /**
     * Content detail / editor view.
     */
    public function show(Request $request, Brand $brand, BrandContentDraft $draft)
    {
        $this->authoriseBrand($brand);

        if ($draft->brand_id !== $brand->id || $draft->user_id !== Auth::id()) {
            abort(403);
        }

        $draft->load(['variations', 'revisions', 'trendSources.trendingTopic']);

        return view('content.show', compact('brand', 'draft'));
    }

    private function authoriseBrand(Brand $brand): void
    {
        if ($brand->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
