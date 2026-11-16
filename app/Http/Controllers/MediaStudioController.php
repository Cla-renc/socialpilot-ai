<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaGenerationJob;
use App\Models\MediaStoryboard;
use App\Models\MediaVisualSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MediaStudioController extends Controller
{
    /**
     * Media Studio dashboard overview with stats and recent assets.
     */
    public function dashboard(Request $request): View
    {
        $userId = Auth::id();

        $stats = [
            'total_assets' => MediaAsset::where('user_id', $userId)->count(),
            'images'       => MediaAsset::where('user_id', $userId)->where('asset_type', 'image')->count(),
            'videos'       => MediaAsset::where('user_id', $userId)->where('asset_type', 'video')->count(),
            'storyboards'  => MediaStoryboard::where('user_id', $userId)->count(),
            'failed_jobs'  => MediaGenerationJob::where('user_id', $userId)->where('status', 'failed')->count(),
        ];

        $recent_assets = MediaAsset::where('user_id', $userId)
            ->with('brand')
            ->latest()
            ->take(6)
            ->get();

        $brands = Brand::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('media.dashboard', compact('stats', 'recent_assets', 'brands'));
    }

    /**
     * AI Image Generator interface.
     */
    public function generator(Request $request): View
    {
        $userId = Auth::id();
        $brands = Brand::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $objectives = [
            'social_post'          => 'Social Media Post',
            'promotional_poster'   => 'Promotional Poster',
            'quote_card'           => 'Quote Card',
            'educational_graphic'  => 'Educational Graphic',
            'product_ad'           => 'Product Advertisement',
            'brand_awareness'      => 'Brand Awareness',
            'event_announcement'   => 'Event Announcement',
            'campaign_creative'    => 'Campaign Creative',
            'thumbnail'            => 'Thumbnail',
            'background'           => 'Background / Wallpaper',
            'custom'               => 'Custom Creative',
        ];

        $visual_styles = [
            'cinematic'     => 'Cinematic',
            'minimalist'    => 'Minimalist',
            'corporate'     => 'Corporate / Clean',
            'commercial'    => 'Commercial / Studio',
            'educational'   => 'Educational / Infographic',
            'inspirational' => 'Inspirational',
            'editorial'     => 'Editorial / High Fashion',
        ];

        $platforms = ['instagram', 'facebook', 'tiktok', 'youtube', 'linkedin'];
        $aspect_ratios = ['1:1', '4:5', '9:16', '16:9', '1.91:1'];

        $selected_brand_id = (int) $request->query('brand_id', $brands->first()?->id);

        return view('media.generator', compact(
            'brands',
            'objectives',
            'visual_styles',
            'platforms',
            'aspect_ratios',
            'selected_brand_id'
        ));
    }

    /**
     * Media Library with search and filters.
     */
    public function library(Request $request): View
    {
        $userId = Auth::id();
        $brands = Brand::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $query = MediaAsset::where('user_id', $userId)->with('brand');

        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        }

        if ($assetType = $request->query('asset_type')) {
            $query->where('asset_type', $assetType);
        }

        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('generation_prompt', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $assets = $query->latest()->paginate(20)->withQueryString();

        $filters = [
            'brand_id'   => $request->query('brand_id', ''),
            'asset_type' => $request->query('asset_type', ''),
            'platform'   => $request->query('platform', ''),
            'search'     => $request->query('search', ''),
        ];

        return view('media.library', compact('brands', 'assets', 'filters'));
    }

    /**
     * Video Studio & Storyboard Generator interface.
     */
    public function videoStudio(Request $request): View
    {
        $userId = Auth::id();
        $brands = Brand::where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $platforms = ['tiktok', 'instagram', 'youtube', 'facebook', 'linkedin'];
        $aspect_ratios = ['9:16', '16:9', '1:1', '4:5'];
        $selected_brand_id = (int) $request->query('brand_id', $brands->first()?->id);

        return view('media.video_studio', compact(
            'brands',
            'platforms',
            'aspect_ratios',
            'selected_brand_id'
        ));
    }

    /**
     * Brand Visual Settings form (GET).
     */
    public function visualSettings(Request $request, Brand $brand): View
    {
        $this->authoriseBrand($brand);

        $settings = MediaVisualSetting::firstOrCreate(
            ['brand_id' => $brand->id],
            [
                'visual_identity'                  => 'modern',
                'primary_color'                    => '#8b5cf6',
                'secondary_color'                  => '#22d3ee',
                'accent_color'                     => '#34d399',
                'background_color'                 => '#0f172a',
                'text_color'                       => '#f8fafc',
                'primary_font'                     => 'Inter',
                'secondary_font'                   => 'Roboto',
                'font_size_base'                   => '16px',
                'logo_placement'                   => 'bottom_right',
                'preferred_platforms'              => ['instagram', 'linkedin'],
                'preferred_aspect_ratios'          => ['1:1', '16:9'],
                'image_generation_style_prompt'    => 'Professional, high quality, vibrant studio lighting, clean background',
                'image_generation_negative_prompt' => 'blurry, low quality, distorted, extra limbs, ugly, watermark',
                'preferred_image_provider'         => 'openai',
                'preferred_video_provider'         => 'runway',
            ]
        );

        return view('media.visual_settings', compact('brand', 'settings'));
    }

    /**
     * Save Brand Visual Settings (POST).
     */
    public function saveVisualSettings(Request $request, Brand $brand): RedirectResponse
    {
        $this->authoriseBrand($brand);

        $validated = $request->validate([
            'visual_identity'                  => 'nullable|string|max:100',
            'primary_color'                    => 'nullable|string|max:30',
            'secondary_color'                  => 'nullable|string|max:30',
            'accent_color'                     => 'nullable|string|max:30',
            'background_color'                 => 'nullable|string|max:30',
            'text_color'                       => 'nullable|string|max:30',
            'primary_font'                     => 'nullable|string|max:100',
            'secondary_font'                   => 'nullable|string|max:100',
            'font_size_base'                   => 'nullable|string|max:20',
            'logo_placement'                   => 'nullable|string|max:50',
            'preferred_platforms'              => 'nullable|array',
            'preferred_aspect_ratios'          => 'nullable|array',
            'image_generation_style_prompt'    => 'nullable|string|max:2000',
            'image_generation_negative_prompt' => 'nullable|string|max:2000',
            'preferred_image_provider'         => 'nullable|string|max:50',
            'preferred_video_provider'         => 'nullable|string|max:50',
        ]);

        MediaVisualSetting::updateOrCreate(
            ['brand_id' => $brand->id],
            $validated
        );

        return redirect()->route('brands.visual-settings', $brand)
            ->with('success', 'Visual brand settings updated successfully.');
    }

    /**
     * Ensure the authenticated user owns the specified brand.
     */
    private function authoriseBrand(Brand $brand): void
    {
        if ($brand->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to brand visual settings.');
        }
    }
}
