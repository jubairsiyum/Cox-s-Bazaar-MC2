<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\ProductService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     *
     * Strict branch isolation — the Products section is availability-based:
     *  - Super Admin sees the full shared catalog;
     *  - branch users see only the products currently in stock at their own
     *    branch. When they search for a product that is NOT available there,
     *    its details are still shown together with the branch(es) that do
     *    have it in stock (name + available quantity) — "out of stock here,
     *    available at X";
     *  - restricted users without a branch assignment see nothing.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $user = $request->user();

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $catalogQuery = Product::query()
            ->with(['brand', 'category', 'location'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')));

        // Super Admin: the catalog, defaulting to the Main Warehouse branch,
        // with a branch filter to switch between branches.
        if (BranchScope::isUnrestricted($user)) {
            $selectedLocationId = $request->filled('location_id')
                ? $request->integer('location_id')
                : Location::mainWarehouse()?->id;

            return view('products.index', [
                'products' => (clone $catalogQuery)
                    ->when($selectedLocationId !== null, fn ($q) => $q->where('location_id', $selectedLocationId))
                    ->latest('id')
                    ->paginate($perPage)
                    ->withQueryString(),
                'perPage' => $perPage,
                'brands' => Brand::orderBy('name')->get(),
                'categories' => Category::orderBy('name')->get(),
                'locations' => Location::active()->orderBy('name')->get(),
                'selectedLocationId' => $selectedLocationId,
                'catalogScopeIds' => null,
                'branchQtyByProduct' => [],
                'crossBranchResults' => collect(),
            ]);
        }

        $branchId = BranchScope::locationId($user);
        $quantitiesByUnit = $branchId === null ? [] : StockService::quantitiesByUnitAt($branchId);

        // Unassigned users see no operational data at all.
        $unitProductIds = $quantitiesByUnit === []
            ? collect()
            : Unit::query()->whereIn('id', array_keys($quantitiesByUnit))->pluck('product_id', 'id');

        $availableProductIds = $unitProductIds
            ->filter(fn ($productId, $unitId): bool => ($quantitiesByUnit[$unitId] ?? 0) > 0)
            ->values()
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        $branchQtyByProduct = $unitProductIds
            ->mapWithKeys(fn ($productId, $unitId): array => [
                (int) $productId => $quantitiesByUnit[(int) $unitId] ?? 0,
            ])
            ->all();

        $products = (clone $catalogQuery)
            ->whereIn('id', $availableProductIds)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        // Cross-branch search: products matching the search that are not
        // available at the user's own branch, with the branch(es) that do
        // have them in stock (name + available quantity). Only assigned
        // users get this — unassigned users see nothing operational.
        $crossBranchResults = collect();

        if ($request->filled('search') && $branchId !== null) {
            $availableLookup = array_flip($availableProductIds);

            $missing = (clone $catalogQuery)
                ->latest('id')
                ->limit(50)
                ->get()
                ->reject(fn (Product $product): bool => isset($availableLookup[$product->id]));

            $availability = StockService::availabilityAcross(
                $missing->map(fn (Product $product): ?int => $product->stockRow?->id)->filter()->all()
            )->groupBy('unit_id');

            $crossBranchResults = $missing->values()->map(fn (Product $product): array => [
                'product' => $product,
                'branches' => $availability->get($product->stockRow?->id, collect()),
            ]);
        }

        return view('products.index', [
            'products' => $products,
            'perPage' => $perPage,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'locations' => collect(),
            'selectedLocationId' => null,
            'catalogScopeIds' => $availableProductIds,
            'branchQtyByProduct' => $branchQtyByProduct,
            'crossBranchResults' => $crossBranchResults,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', [
            'product' => new Product,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'locations' => Location::active()->orderBy('name')->get(),
            'mainWarehouseId' => Location::mainWarehouse()?->id,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $quantity = isset($validated['quantity']) ? (int) $validated['quantity'] : 0;
        $unitOfMeasure = $validated['unit'] ?? 'Pcs';
        unset($validated['quantity'], $validated['unit']);
        $validated['created_by'] = $request->user()->id;
        $validated['photo'] = $this->storePhoto($request);

        $product = ProductService::create($validated, $quantity, $unitOfMeasure);

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.edit', [
            'product' => $product,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'locations' => Location::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $original = $product->getOriginal();
        $validated = $request->validated();
        $quantity = isset($validated['quantity']) ? (int) $validated['quantity'] : null;
        $unitOfMeasure = $validated['unit'] ?? null;
        unset($validated['quantity'], $validated['unit']);

        $validated['photo'] = $this->storePhoto($request) ?? $product->photo;

        $product->update($validated);

        $this->deleteReplacedPhoto($request, $original);

        $this->syncStockRow($product, $quantity, $unitOfMeasure, $request->user()->id);

        AuditService::updated($product, $original, "Product '{$product->name}' ({$product->product_id}) updated.");

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' updated successfully.");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        // A product variant and its stock row live and die together.
        $product->stockRow?->delete();
        $product->delete();

        AuditService::deleted($product, "Product '{$product->name}' ({$product->product_id}) deleted.");

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' deleted successfully.");
    }

    /**
     * Toggle active/inactive status of the specified product.
     */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $this->authorize('toggleStatus', $product);

        $oldStatus = $product->status;
        $newStatus = $product->status === 'active' ? 'inactive' : 'active';

        $product->update(['status' => $newStatus]);

        AuditService::log(
            'updated',
            $product,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Product '{$product->name}' ({$product->product_id}) status changed to {$newStatus}."
        );

        return back()->with('success', "Product '{$product->name}' status updated to ".ucfirst($newStatus).'.');
    }

    /**
     * Keep the product's single stock row in sync with the form's quantity and unit.
     *
     * Quantity changes go through the stock ledger: Super Admin (and other
     * unrestricted users) correct the shared stock row as a whole
     * (StockService::adjust), while branch users record a stock count of
     * THEIR OWN BRANCH — the form quantity is the counted quantity at their
     * branch and only that branch's ledger balance moves
     * (StockService::countAt). Users without a branch assignment change no
     * stock at all.
     */
    private function syncStockRow(Product $product, ?int $quantity, ?string $unitOfMeasure, int $userId): void
    {
        $stockRow = $product->stockRow;

        if (! $stockRow) {
            $product->stockRow()->create([
                'quantity' => $quantity ?? 0,
                'unit' => $unitOfMeasure ?? 'Pcs',
                'created_by' => $userId,
            ]);

            return;
        }

        if ($quantity !== null) {
            $branchId = BranchScope::locationId(Auth::user());

            if (Auth::user() !== null && BranchScope::isRestricted(Auth::user())) {
                if ($branchId !== null && $quantity !== StockService::quantityAt($stockRow, $branchId)) {
                    StockService::countAt($stockRow, $quantity, $branchId);
                }
            } elseif ($quantity !== $stockRow->quantity) {
                StockService::adjust($stockRow, $quantity);
            }
        }

        if ($unitOfMeasure !== null && $unitOfMeasure !== $stockRow->unit) {
            $stockRow->update(['unit' => $unitOfMeasure]);
        }
    }

    /**
     * Store the uploaded photo and return its public path.
     */
    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('product-photos', 'public')
            : null;
    }

    /**
     * Remove the previous photo when a replacement was uploaded.
     *
     * @param  array<string, mixed>  $original
     */
    private function deleteReplacedPhoto(Request $request, array $original): void
    {
        $previous = $original['photo'] ?? null;

        if ($previous && $request->hasFile('photo')) {
            Storage::disk('public')->delete($previous);
        }
    }
}
