<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ProductRequestDeliveryStatus;
use App\Enums\ProductRequestStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Product\SubmitProductRequestRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\ProductRequestNotifier;
use App\Services\ProductService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Branches ask for new catalog products; only the Super Admin decides. A
 * request creates nothing until approved, and approval creates the product
 * through the same ProductService the Add Product form uses.
 */
class ProductRequestController extends Controller
{
    /**
     * The requests the viewer may see, optionally filtered by status.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductRequest::class);

        $status = $request->string('status')->toString();
        $status = ProductRequestStatus::tryFrom($status) !== null ? $status : '';

        $visible = ProductRequest::query()->visibleTo($request->user());

        $statusCounts = (clone $visible)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('products.requests.index', [
            'requests' => (clone $visible)
                ->with(['location', 'requester', 'reviewer', 'createdProduct'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'currentStatus' => $status,
            'statusCounts' => [
                'all' => (int) $statusCounts->sum(),
                'pending' => (int) ($statusCounts[ProductRequestStatus::Pending->value] ?? 0),
                'approved' => (int) ($statusCounts[ProductRequestStatus::Approved->value] ?? 0),
                'rejected' => (int) ($statusCounts[ProductRequestStatus::Rejected->value] ?? 0),
            ],
        ]);
    }

    /**
     * Show the new product request form (the Add Product form, submitted for approval).
     */
    public function create(): View
    {
        $this->authorize('create', ProductRequest::class);

        return view('products.requests.create', [
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    /**
     * Record the request for the Super Admin. The requesting branch is always
     * the acting user's own branch.
     */
    public function store(SubmitProductRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $productRequest = ProductRequest::create([
            'location_id' => BranchScope::locationId($request->user()),
            'status' => ProductRequestStatus::Pending,
            'name' => $validated['name'],
            'photo' => $this->storePhoto($request),
            'brand_id' => $validated['brand_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'model' => $validated['model'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'storage' => $validated['storage'] ?? null,
            'color' => $validated['color'] ?? null,
            'purchase_price' => $validated['purchase_price'] ?? null,
            'sale_price' => $validated['sale_price'] ?? null,
            'quantity' => (int) ($validated['quantity'] ?? 0),
            'unit' => $validated['unit'] ?? 'Pcs',
            'product_status' => $validated['status'] ?? 'active',
            'note' => $validated['note'] ?? null,
            'requested_by' => $request->user()->id,
        ]);

        AuditService::created($productRequest, "Product request '{$productRequest->displayName()}' submitted.");

        ProductRequestNotifier::submitted($productRequest);

        return redirect()->route('products.requests.show', $productRequest)
            ->with('success', "Product request '{$productRequest->displayName()}' submitted. It is added to the catalog once the Super Admin approves it.");
    }

    /**
     * Show the details of a request and, for the Super Admin, its decision.
     */
    public function show(ProductRequest $productRequest): View
    {
        $this->authorize('view', $productRequest);

        $productRequest->load(['location', 'brand', 'category', 'requester', 'reviewer', 'createdProduct']);

        return view('products.requests.show', [
            'productRequest' => $productRequest,
        ]);
    }

    /**
     * Approve a request: the product is created through ProductService, exactly
     * like the Add Product form does. Duplicates are re-checked at this point,
     * since the catalog may have changed since the request was submitted.
     */
    public function approve(ProductRequest $productRequest): RedirectResponse
    {
        $this->authorize('approve', $productRequest);

        $result = DB::transaction(function () use ($productRequest): Product|string {
            $locked = ProductRequest::query()->whereKey($productRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return "Product request '{$locked->displayName()}' is no longer awaiting approval.";
            }

            $duplicate = ProductService::duplicateMessage($locked->name, $locked->storage, $locked->color, $locked->barcode);

            if ($duplicate !== null) {
                return "Product request '{$locked->displayName()}' cannot be approved: {$duplicate}";
            }

            $original = $locked->getOriginal();

            $product = ProductService::create(
                $locked->toProductAttributes() + [
                    'created_by' => Auth::id(),
                    'location_id' => Location::mainWarehouse()?->id,
                ],
                (int) $locked->quantity,
                $locked->unit,
            );

            $locked->update([
                'status' => ProductRequestStatus::Approved,
                'delivery_status' => ProductRequestDeliveryStatus::InTransit,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'created_product_id' => $product->id,
            ]);

            AuditService::updated($locked, $original, "Product request '{$locked->displayName()}' approved. Product '{$product->name}' ({$product->product_id}) created.");

            return $product;
        });

        if (is_string($result)) {
            return back()->with('error', $result);
        }

        $productRequest->refresh();

        ProductRequestNotifier::approved($productRequest, Auth::user(), $result);

        return redirect()->route('products.requests.show', $productRequest)
            ->with('success', "Product '{$result->displayName()}' added to the catalog.");
    }

    /**
     * Reject a request with a reason. Nothing is added to the catalog.
     */
    public function reject(Request $request, ProductRequest $productRequest): RedirectResponse
    {
        $this->authorize('reject', $productRequest);

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $original = $productRequest->getOriginal();

        $productRequest->update([
            'status' => ProductRequestStatus::Rejected,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        // A rejected request never becomes a product, so its photo is not kept.
        if ($original['photo'] ?? null) {
            Storage::disk('public')->delete($original['photo']);
        }

        AuditService::updated($productRequest, $original, "Product request '{$productRequest->displayName()}' rejected.");

        $productRequest->refresh();

        ProductRequestNotifier::rejected($productRequest, Auth::user(), $reason);

        return redirect()->route('products.requests.show', $productRequest)
            ->with('success', "Product request '{$productRequest->displayName()}' rejected.");
    }

    /**
     * The requesting branch receives the approved product: the delivery leg
     * flips to "Delivered" and the product's opening stock moves from the
     * Main Warehouse (its assigned branch) to the branch's ledger.
     */
    public function receive(ProductRequest $productRequest): RedirectResponse
    {
        $this->authorize('receive', $productRequest);

        if (! $productRequest->isApproved() || ! $productRequest->isInTransit()) {
            return back()->with('error', "Product request '{$productRequest->displayName()}' is not awaiting delivery.");
        }

        try {
            DB::transaction(function () use ($productRequest): void {
                $locked = ProductRequest::query()->whereKey($productRequest->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isApproved() || ! $locked->isInTransit()) {
                    throw new \RuntimeException('No longer awaiting delivery.');
                }

                $unit = $locked->createdProduct?->stockRow;
                $warehouseId = $locked->createdProduct?->location_id;
                $branchId = $locked->location_id;
                $quantity = (int) $locked->quantity;

                if ($unit !== null && $quantity > 0 && $branchId !== null) {
                    StockService::transferBetweenLocations(
                        $unit,
                        $quantity,
                        $warehouseId,
                        $branchId,
                        $locked,
                        "Product request '{$locked->displayName()}' received at the branch."
                    );
                }

                $locked->update([
                    'delivery_status' => ProductRequestDeliveryStatus::Delivered,
                    'delivered_at' => now(),
                    'received_by' => Auth::id(),
                ]);
            });
        } catch (\RuntimeException) {
            return back()->with('error', "Product request '{$productRequest->displayName()}' is no longer awaiting delivery.");
        } catch (InsufficientStockException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $productRequest->refresh();

        AuditService::updated($productRequest, $productRequest->getOriginal(), "Product request '{$productRequest->displayName()}' received at ".($productRequest->location?->name ?? 'the branch').'.');

        ProductRequestNotifier::delivered($productRequest, Auth::user());

        return redirect()->route('products.requests.show', $productRequest)
            ->with('success', "Product request '{$productRequest->displayName()}' received at ".($productRequest->location?->name ?? 'the branch').'.');
    }

    /**
     * Store the uploaded photo with the request and return its public path.
     */
    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo')
            ? $request->file('photo')->store('product-photos', 'public')
            : null;
    }
}
