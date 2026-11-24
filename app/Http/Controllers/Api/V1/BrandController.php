<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BrandResource;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Jobs\PurgeUserMediaStorageJob;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function __construct(protected BrandService $brandService) {}

    public function index(Request $request)
    {
        $query = Brand::query()
            ->where('user_id', $request->user()->id)
            ->with('settings')
            ->withCount(['socialAccounts as connected_accounts_count' => fn ($accounts) => $accounts->connected()]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($brands) use ($search): void {
                $brands->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('industry', 'like', '%'.$search.'%')
                    ->orWhere('niche', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('industry')) {
            $query->where('industry', $request->query('industry'));
        }

        $sort = $request->validate([
            'sort' => ['nullable', Rule::in(['name', 'industry', 'status', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $brands = $query->orderBy($sort['sort'] ?? 'created_at', $sort['direction'] ?? 'desc')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        return response()->json([
            'data' => $brands->getCollection()->map(fn (Brand $brand) => BrandResource::make($brand)->resolve($request)),
            'meta' => [
                'current_page' => $brands->currentPage(),
                'last_page' => $brands->lastPage(),
                'per_page' => $brands->perPage(),
                'total' => $brands->total(),
            ],
        ]);
    }

    public function store(StoreBrandRequest $request)
    {
        $brand = $this->brandService->createBrand($request->validated(), $request->user()->id);

        return response()->json([
            'data' => BrandResource::make($brand->fresh(['settings']))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Brand $brand)
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        return response()->json([
            'data' => BrandResource::make($brand->load('settings')->loadCount([
                'socialAccounts as connected_accounts_count' => fn ($accounts) => $accounts->connected(),
            ]))->resolve($request),
        ]);
    }

    public function overview(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);

        return response()->json([
            'data' => [
                'brand_id' => $brand->id,
                'status' => $brand->status,
                'connected_accounts' => $brand->socialAccounts()->connected()->count(),
                'content_drafts' => $brand->contentDrafts()->count(),
                'posts' => $brand->posts()->count(),
                'campaigns' => $brand->campaigns()->count(),
            ],
        ]);
    }

    public function settings(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);

        return response()->json(['data' => $brand->settings]);
    }

    public function updateStatus(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
        ]);
        $brand->update(['status' => $validated['status']]);

        return response()->json(['data' => ['id' => $brand->id, 'status' => $brand->status]]);
    }

    public function activate(Request $request, Brand $brand)
    {
        $this->authorizeBrand($request, $brand);
        $brand->update(['status' => 'active']);

        return response()->json(['data' => ['id' => $brand->id, 'status' => $brand->status]]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        $brand = $this->brandService->updateBrand($brand, $request->validated());

        return response()->json([
            'data' => BrandResource::make($brand->fresh(['settings']))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Brand $brand)
    {
        $this->authorize('delete', $brand);

        $brandId  = $brand->id;
        $logoPath = $brand->logo_path;
        $userId   = $request->user()->id;

        DB::transaction(function () use ($brand, $brandId, $logoPath, $userId): void {
            // Dispatch storage cleanup BEFORE the SQL cascade removes the brand row,
            // so the job's active-brand safeguard correctly identifies the brand as deleted.
            PurgeUserMediaStorageJob::dispatch(
                [$brandId],
                $logoPath ? [$logoPath] : [],
                $userId,
            );

            $brand->delete();
        });

        return response()->json([
            'message' => 'Brand deleted successfully.',
        ]);
    }

    private function authorizeBrand(Request $request, Brand $brand): void
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');
    }
}
