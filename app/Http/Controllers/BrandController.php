<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function __construct(
        protected BrandService $brandService
    ) {}

    public function index(Request $request): View
    {
        $query = Brand::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['socialAccounts as connected_accounts_count' => fn ($accounts) => $accounts->connected()]);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($brands) use ($search): void {
                $brands->where('name', 'like', '%'.$search.'%')
                    ->orWhere('industry', 'like', '%'.$search.'%')
                    ->orWhere('niche', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
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
            ->paginate(12)
            ->withQueryString();
        $industries = Brand::query()->where('user_id', $request->user()->id)
            ->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry');

        return view('brands.index', compact('brands', 'industries'));
    }

    public function create(): View
    {
        return view('brands.create', [
            'objectives' => config('brand.objectives'),
            'contentCategories' => config('brand.content_categories'),
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $this->brandService->createBrand($request->validated(), $request->user()->id);

        return redirect()->route('brands.index')->with('success', 'Brand created successfully.');
    }

    public function show(Request $request, Brand $brand): View
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');
        $brand->load('settings')->loadCount([
            'socialAccounts as connected_accounts_count' => fn ($accounts) => $accounts->connected(),
            'contentDrafts',
            'posts',
            'campaigns',
        ]);

        return view('brands.show', compact('brand'));
    }

    public function edit(Request $request, Brand $brand): View
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');
        $brand->load('settings');

        return view('brands.edit', [
            'brand' => $brand,
            'objectives' => config('brand.objectives'),
            'contentCategories' => config('brand.content_categories'),
        ]);
    }

    public function settings(Request $request, Brand $brand): View
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');
        $brand->load('settings');

        return view('brands.settings', [
            'brand' => $brand,
            'objectives' => config('brand.objectives'),
        ]);
    }

    public function updateSettings(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);
        $this->brandService->updateBrand($brand, $request->validated());

        return redirect()->route('brands.settings', $brand)->with('success', 'Brand settings updated.');
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $this->brandService->updateBrand($brand, $request->validated());

        return redirect()->route('brands.show', $brand)->with('success', 'Brand updated successfully.');
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand deleted successfully.');
    }
}
