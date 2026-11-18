<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\StockAdjustmentType;
use App\Exports\StockAdjustmentExport;
use App\Helpers\Money;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UpdateStockAdjustmentRequest;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\LocationScope;
use App\Services\NotificationService;
use App\Services\PurchaseApprovalNotifier;
use App\Services\StockAdjustmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockAdjustmentController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['reference_no', 'adjustment_date', 'type', 'total_amount', 'reason'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'adjustment_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $query = StockAdjustment::query()
            ->with(['creator', 'location'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('approval_status', $request->string('status')->toString()))
            ->when($request->filled('location_id'), fn ($q) => $q->where('location_id', $request->integer('location_id')))
            ->when($request->filled('branch'), fn ($q) => $q->whereIn(
                'location_id',
                Location::query()->where('branch_id', $request->integer('branch'))->pluck('id')
            ));

        // Outlet Managers see the adjustments of their location/branch only.
        LocationScope::scopeQuery($query, $request->user(), 'location_id');

        $adjustments = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('stock-adjustments.index', [
            'adjustments' => $adjustments,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'statuses' => ApprovalStatus::options(),
            'branches' => Branch::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StockAdjustment::class);

        return view('stock-adjustments.create', [
            'types' => StockAdjustmentType::options(),
            'typeHints' => collect(StockAdjustmentType::cases())->mapWithKeys(fn (StockAdjustmentType $type) => [$type->value => $type->hint()])->all(),
            'locations' => $this->scopedLocations(),
            'items' => $this->formItems($request),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'];

        $adjustment = DB::transaction(function () use ($request, $validated, $items) {
            $adjustment = StockAdjustment::create([
                'reference_no' => $validated['reference_no'] ?? null,
                'adjustment_date' => $validated['adjustment_date'],
                'location_id' => $validated['location_id'],
                'type' => $validated['type'],
                'approval_status' => ApprovalStatus::Pending,
                'total_amount' => $this->itemsTotal($items),
                'reason' => $validated['reason'],
                'created_by' => $request->user()->id,
            ]);

            StockAdjustmentService::saveItems($adjustment, $items);

            return $adjustment;
        });

        AuditService::created($adjustment, "Stock adjustment '{$adjustment->reference_no}' submitted for approval.");

        $this->notifyManagers(
            $adjustment,
            "Stock adjustment '{$adjustment->reference_no}' awaits your approval.",
            sprintf(
                '%s · %s · %s, submitted by %s.',
                $adjustment->type->label(),
                $adjustment->location?->name ?? 'Unknown location',
                Money::format($adjustment->total_amount),
                $request->user()?->name ?? 'Unknown user'
            ),
            'warning'
        );

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$adjustment->reference_no}' submitted for approval.");
    }

    public function edit(StockAdjustment $stockAdjustment): View|RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);

        if (! $stockAdjustment->isPending()) {
            return redirect()->route('stock-adjustments.index')
                ->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' can no longer be edited because it has already been decided. Create a new adjustment to correct it.");
        }

        return view('stock-adjustments.edit', [
            'stockAdjustment' => $stockAdjustment,
            'types' => StockAdjustmentType::options(),
            'typeHints' => collect(StockAdjustmentType::cases())->mapWithKeys(fn (StockAdjustmentType $type) => [$type->value => $type->hint()])->all(),
            'locations' => $this->scopedLocations(),
            'items' => $this->oldItems() !== [] ? $this->hydrateItems($this->oldItems()) : $this->presentItems($stockAdjustment),
        ]);
    }

    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        if (! $stockAdjustment->isPending()) {
            return redirect()->route('stock-adjustments.index')
                ->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' can no longer be edited because it has already been decided. Create a new adjustment to correct it.");
        }

        $original = $stockAdjustment->getOriginal();
        $validated = $request->validated();
        $items = $validated['items'];

        DB::transaction(function () use ($stockAdjustment, $validated, $items) {
            $stockAdjustment->update([
                'reference_no' => $validated['reference_no'] ?: $stockAdjustment->reference_no,
                'adjustment_date' => $validated['adjustment_date'],
                'location_id' => $validated['location_id'],
                'type' => $validated['type'],
                'total_amount' => $this->itemsTotal($items),
                'reason' => $validated['reason'],
            ]);

            StockAdjustmentService::saveItems($stockAdjustment, $items);
        });

        AuditService::updated($stockAdjustment, $original, "Stock adjustment '{$stockAdjustment->reference_no}' updated.");

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$stockAdjustment->reference_no}' updated successfully.");
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('delete', $stockAdjustment);

        if ($stockAdjustment->isApproved() && ! $stockAdjustment->isReversible()) {
            return back()->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' can no longer be deleted because stock has moved since it was posted. Create a new adjustment to correct it.");
        }

        DB::transaction(function () use ($stockAdjustment) {
            if ($stockAdjustment->isApproved()) {
                StockAdjustmentService::reverse($stockAdjustment);
            }

            $stockAdjustment->delete();
        });

        AuditService::deleted($stockAdjustment, "Stock adjustment '{$stockAdjustment->reference_no}' deleted.");

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$stockAdjustment->reference_no}' deleted successfully.");
    }

    /**
     * Approve (apply) an adjustment request: stock only moves once the
     * responsible manager signs off.
     */
    public function approve(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('approve', $stockAdjustment);

        if (! $stockAdjustment->isPending()) {
            return back()->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' has already been decided.");
        }

        $original = $stockAdjustment->getOriginal();

        DB::transaction(function () use ($stockAdjustment) {
            $stockAdjustment->update([
                'approval_status' => ApprovalStatus::Approved,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            StockAdjustmentService::apply($stockAdjustment);
        });

        AuditService::posted($stockAdjustment, "Stock adjustment '{$stockAdjustment->reference_no}' approved and applied.");

        $this->notifyCreator(
            $stockAdjustment,
            "Stock adjustment '{$stockAdjustment->reference_no}' approved.",
            sprintf(
                'Your adjustment for %s was approved and applied by %s.',
                $stockAdjustment->location?->name ?? 'Unknown location',
                Auth::user()?->name ?? 'an authorized manager'
            ),
            'success'
        );

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$stockAdjustment->reference_no}' approved and applied.");
    }

    /**
     * Reject an adjustment request: nothing is posted to stock.
     */
    public function reject(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('reject', $stockAdjustment);

        if (! $stockAdjustment->isPending()) {
            return back()->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' has already been decided.");
        }

        $original = $stockAdjustment->getOriginal();

        $stockAdjustment->update(['approval_status' => ApprovalStatus::Rejected]);

        AuditService::updated($stockAdjustment, $original, "Stock adjustment '{$stockAdjustment->reference_no}' rejected.");

        $this->notifyCreator(
            $stockAdjustment,
            "Stock adjustment '{$stockAdjustment->reference_no}' rejected.",
            sprintf(
                'Your adjustment for %s was rejected by %s. No stock was changed.',
                $stockAdjustment->location?->name ?? 'Unknown location',
                Auth::user()?->name ?? 'an authorized manager'
            ),
            'error'
        );

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$stockAdjustment->reference_no}' rejected.");
    }

    /**
     * Search stock lines (products) for the adjustment form.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $units = Unit::query()
            ->with('product')
            ->search($request->string('q')->toString())
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->limit(10)
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'product_name' => $unit->displayName(),
                'unit' => $unit->unit,
                'quantity' => $unit->quantity,
                'purchase_price' => (float) ($unit->product?->purchase_price ?? 0),
            ]);

        return response()->json(['success' => true, 'items' => $units]);
    }

    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        [$columns, $adjustments] = $this->exportData($request);

        return view('stock-adjustments.pdf', ['adjustments' => $adjustments, 'columns' => $columns]);
    }

    public function printView(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        [$columns, $adjustments] = $this->exportData($request);

        return view('stock-adjustments.print', ['adjustments' => $adjustments, 'columns' => $columns]);
    }

    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $filename = 'stock_adjustments_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new StockAdjustmentExport($request->string('search')->toString() ?: null, $request->string('columns')->toString() ?: null),
            $filename
        );
    }

    /**
     * @return array{0: array<string, string>, 1: Collection<int, StockAdjustment>}
     */
    private function exportData(Request $request): array
    {
        $search = $request->string('search')->toString();
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'adjustment_date' => 'Date',
            'reference_no' => 'Reference No',
            'type' => 'Adjustment type',
            'total_amount' => 'Total Amount',
            'reason' => 'Reason',
            'creator' => 'Added By',
        ];

        if ($columnsParam !== '') {
            $requested = array_filter(array_map('trim', explode(',', $columnsParam)));
            $columns = [];
            foreach ($requested as $col) {
                if (isset($availableColumns[$col])) {
                    $columns[$col] = $availableColumns[$col];
                }
            }
            if (empty($columns)) {
                $columns = $availableColumns;
            }
        } else {
            $columns = $availableColumns;
        }

        $adjustments = StockAdjustment::query()
            ->with('creator')
            ->when($search !== '', fn ($q) => $q->search($search))
            ->orderBy('adjustment_date', 'desc')
            ->get();

        return [$columns, $adjustments];
    }

    /**
     * Line items for the form: old input, a pre-filled stock line (when opened
     * from Stock Levels) or an empty list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formItems(Request $request): array
    {
        if ($this->oldItems() !== []) {
            return $this->hydrateItems($this->oldItems());
        }

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->with('product')->find($request->integer('unit_id'));

            if ($unit) {
                return [[
                    'unit_id' => (int) $unit->id,
                    'product_name' => $unit->displayName(),
                    'unit' => $unit->unit,
                    'quantity' => (int) $unit->quantity,
                    'unit_price' => (float) ($unit->product?->purchase_price ?? 0),
                ]];
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function oldItems(): array
    {
        return collect(old('items', []))->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateItems(array $rows): array
    {
        $units = Unit::query()
            ->with('product')
            ->whereIn('id', collect($rows)->pluck('unit_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        return collect($rows)->map(function (array $row) use ($units) {
            $unit = $units->get((int) ($row['unit_id'] ?? 0));

            return [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'product_name' => $unit?->displayName() ?? 'Unknown product',
                'unit' => $unit?->unit ?? '',
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presentItems(StockAdjustment $stockAdjustment): array
    {
        return $stockAdjustment->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'unit' => $item->unit?->unit ?? '',
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
        ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function itemsTotal(array $items): float
    {
        return round(array_sum(array_map(
            fn (array $item) => (int) $item['quantity'] * (float) $item['unit_price'],
            $items
        )), 2);
    }

    /**
     * Locations the current user may book adjustments against.
     *
     * @return Collection<int, Location>
     */
    private function scopedLocations(): Collection
    {
        $query = Location::query()->active()->orderBy('name');

        LocationScope::scopeQuery($query, Auth::user(), 'id');

        return $query->get();
    }

    /**
     * Notify the managers of the location/branch the adjustment happened at.
     */
    private function notifyManagers(StockAdjustment $stockAdjustment, string $title, string $body, string $type): void
    {
        NotificationService::notifyMany(
            PurchaseApprovalNotifier::managersFor($stockAdjustment->location_id, $stockAdjustment->location?->branch_id),
            $title,
            $body,
            route('stock-adjustments.index'),
            $type
        );
    }

    /**
     * Notify the creator of an adjustment about a decision taken on it.
     */
    private function notifyCreator(StockAdjustment $stockAdjustment, string $title, string $body, string $type): void
    {
        if ($stockAdjustment->creator === null) {
            return;
        }

        NotificationService::notify($stockAdjustment->creator, $title, $body, route('stock-adjustments.index'), $type);
    }
}
