<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $products = Product::query()
            ->with(['brand', 'category'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'perPage' => $perPage,
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
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

        $product = Product::create($validated);

        // Every product gets a single stock row so it shows up in Units and Transfers.
        $product->stockRow()->create([
            'quantity' => $quantity,
            'unit' => $unitOfMeasure,
            'created_by' => $request->user()->id,
        ]);

        AuditService::created($product, "Product '{$product->name}' ({$product->product_id}) created.");

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
     * Quantity changes go through the stock ledger (StockService::adjust).
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

        if ($quantity !== null && $quantity !== $stockRow->quantity) {
            StockService::adjust($stockRow, $quantity);
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
