<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\StockAdjustmentType;
use App\Exports\StockAdjustmentExport;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UpdateStockAdjustmentRequest;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\LocationScope;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Stock adjustments take effect the moment they are saved: the stock
 * movements are posted right away (exactly once) and there is no approval
 * step. Legacy rows that still carry a pending approval state are treated
 * as applied.
 */
class StockAdjustmentController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['reference_no', 'adjustment_date', 'type', 'total_amount', 'reason'];

    /**
     * Applied-state filters the list can filter by. "Applied" also matches
     * legacy pending rows: the approval workflow is retired and such rows
     * are normalized to applied.
     *
     * @var array<string, string>
     */
    protected const STATUS_FILTERS = [
        'applied' => 'Applied',
        'rejected' => 'Rejected',
    ];

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
            ->when($request->string('status')->toString() === 'applied', fn ($q) => $q->whereIn('approval_status', [ApprovalStatus::Approved->value, ApprovalStatus::Pending->value]))
            ->when($request->string('status')->toString() === 'rejected', fn ($q) => $q->where('approval_status', ApprovalStatus::Rejected->value))
            ->when($request->filled('location_id') && ! LocationScope::isRestricted($request->user()), fn ($q) => $q->where('location_id', $request->integer('location_id')))
            ->when($request->filled('branch') && ! LocationScope::isRestricted($request->user()), fn ($q) => $q->whereIn(
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
            'statuses' => self::STATUS_FILTERS,
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

    /**
     * Save a stock adjustment: the stock effect is applied immediately (the
     * adjustment is recorded as completed — no approval step) and posted to
     * the stock ledger exactly once.
     */
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
                'approval_status' => ApprovalStatus::Approved,
                'total_amount' => $this->itemsTotal($items),
                'reason' => $validated['reason'],
                'created_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            StockAdjustmentService::saveItems($adjustment, $items);
            StockAdjustmentService::apply($adjustment);

            return $adjustment;
        });

        AuditService::created($adjustment, "Stock adjustment '{$adjustment->reference_no}' created and applied to stock.");

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$adjustment->reference_no}' saved and applied to stock.");
    }

    public function edit(StockAdjustment $stockAdjustment): View|RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);

        if ($error = $this->editabilityError($stockAdjustment)) {
            return redirect()->route('stock-adjustments.index')->with('error', $error);
        }

        return view('stock-adjustments.edit', [
            'stockAdjustment' => $stockAdjustment,
            'types' => StockAdjustmentType::options(),
            'typeHints' => collect(StockAdjustmentType::cases())->mapWithKeys(fn (StockAdjustmentType $type) => [$type->value => $type->hint()])->all(),
            'locations' => $this->scopedLocations(),
            'items' => $this->oldItems() !== [] ? $this->hydrateItems($this->oldItems()) : $this->presentItems($stockAdjustment),
        ]);
    }

    /**
     * Update an adjustment: its previous stock effect is reversed and the new
     * quantities are re-posted immediately (exactly once per version), as long
     * as no stock has moved since it was applied.
     */
    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        if ($error = $this->editabilityError($stockAdjustment)) {
            return redirect()->route('stock-adjustments.index')->with('error', $error);
        }

        $original = $stockAdjustment->getOriginal();
        $validated = $request->validated();
        $items = $validated['items'];
        $wasApplied = $stockAdjustment->isApproved();

        DB::transaction(function () use ($request, $stockAdjustment, $validated, $items, $wasApplied): void {
            if ($wasApplied) {
                StockAdjustmentService::reverse($stockAdjustment);
            }

            $stockAdjustment->update([
                'reference_no' => $validated['reference_no'] ?: $stockAdjustment->reference_no,
                'adjustment_date' => $validated['adjustment_date'],
                'location_id' => $validated['location_id'],
                'type' => $validated['type'],
                'total_amount' => $this->itemsTotal($items),
                'reason' => $validated['reason'],
                'approval_status' => ApprovalStatus::Approved,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            StockAdjustmentService::saveItems($stockAdjustment, $items);
            StockAdjustmentService::apply($stockAdjustment);
        });

        AuditService::updated(
            $stockAdjustment,
            $original,
            $wasApplied
                ? "Stock adjustment '{$stockAdjustment->reference_no}' updated: previous stock effect reversed and new quantities applied."
                : "Stock adjustment '{$stockAdjustment->reference_no}' updated and applied to stock."
        );

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
     * Why an adjustment can no longer be edited, or null when it can.
     *
     * Applied adjustments stay editable while nothing has moved since they
     * were posted (their effect is reversed and re-posted on save); rejected
     * legacy rows never touched stock and are immutable history.
     */
    private function editabilityError(StockAdjustment $stockAdjustment): ?string
    {
        if ($stockAdjustment->isRejected()) {
            return "Stock adjustment '{$stockAdjustment->reference_no}' was rejected and can no longer be edited. Create a new adjustment to correct it.";
        }

        if ($stockAdjustment->isApproved() && ! $stockAdjustment->isReversible()) {
            return "Stock adjustment '{$stockAdjustment->reference_no}' can no longer be edited because stock has moved since it was applied. Create a new adjustment to correct it.";
        }

        return null;
    }

    /**
     * Search stock lines (products) for the adjustment form. Quantities shown
     * are scoped to the acting user's own branch (strict branch isolation).
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $units = Unit::query()
            ->with('product')
            ->search($request->string('q')->toString())
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->limit(10)
            ->get();

        $quantities = StockService::scopedQuantitiesFor($units, $request->user());

        $units = $units
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'product_name' => $unit->displayName(),
                'unit' => $unit->unit,
                'quantity' => $quantities[$unit->id] ?? 0,
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
            new StockAdjustmentExport($request->string('search')->toString() ?: null, $request->string('columns')->toString() ?: null, $request->user()),
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
     * from Stocks) or an empty list.
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
}
