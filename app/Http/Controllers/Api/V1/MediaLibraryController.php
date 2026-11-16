<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\MediaLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaLibraryController extends Controller
{
    public function __construct(
        protected MediaLibraryService $libraryService,
    ) {}

    public function index(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $filters = $request->only(['asset_type', 'platform', 'generation_status', 'search', 'sort', 'per_page']);
        $assets  = $this->libraryService->getAssets($request->user(), $brand, $filters);

        return response()->json($assets);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $asset = $this->libraryService->getAsset($request->user(), $id);

        return response()->json([
            'data'  => $asset,
            'asset' => $asset,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asset = $this->libraryService->getAsset($request->user(), $id);

        $validated = $request->validate([
            'title'    => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ]);

        $updated = $this->libraryService->updateAsset($asset, $validated);

        return response()->json([
            'message' => 'Media asset updated successfully.',
            'data'    => $updated,
            'asset'   => $updated,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $asset = $this->libraryService->getAsset($request->user(), $id);

        $this->libraryService->deleteAsset($asset);

        return response()->json([
            'message' => 'Media asset deleted successfully.',
        ]);
    }

    public function upload(Request $request, ?Brand $brand = null): JsonResponse
    {
        if ($brand && $brand->exists) {
            $request->merge(['brand_id' => $brand->id]);
        }

        $validated = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'file'     => ['required', 'file'],
            'title'    => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ]);

        $targetBrand = $brand && $brand->exists ? $brand : Brand::findOrFail($validated['brand_id']);
        abort_unless($targetBrand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $asset = $this->libraryService->uploadAsset(
            $request->user(),
            $targetBrand,
            $request->file('file'),
            $validated
        );

        return response()->json([
            'message' => 'Media file uploaded successfully.',
            'data'    => $asset,
            'asset'   => $asset,
        ], 201);
    }
}
