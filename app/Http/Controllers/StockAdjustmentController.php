<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockAdjustmentType;
use App\Exports\StockAdjustmentExport;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UpdateStockAdjustmentRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\StockAdjustmentService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    protected const SORTABLE = ['reference_no', 'adjustment_date', 'type', 'total_amount', 'total_amount_recovered', 'reason'];

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

        $adjustments = StockAdjustment::query()
            ->with(['location', 'creator'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('stock-adjustments.index', [
            'adjustments' => $adjustments,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StockAdjustment::class);

        return view('stock-adjustments.create', [
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'types' => StockAdjustmentType::options(),
            'items' => $this->hydrateItems(collect(old('items', []))->all()),
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
                'total_amount' => $this->itemsTotal($items),
                'total_amount_recovered' => round((float) ($validated['total_amount_recovered'] ?? 0), 2),
                'reason' => $validated['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            StockAdjustmentService::saveItems($adjustment, $items);
            StockAdjustmentService::apply($adjustment);

            return $adjustment;
        });

        AuditService::created($adjustment, "Stock adjustment '{$adjustment->reference_no}' created.");

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$adjustment->reference_no}' created successfully.");
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('update', $stockAdjustment);

        if (! $stockAdjustment->isReversible()) {
            return redirect()->route('stock-adjustments.index')
                ->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' cannot be edited because stock has moved since it was posted. Create a correction adjustment instead.");
        }

        $items = $this->oldItems() !== []
            ? $this->hydrateItems($this->oldItems())
            : $this->presentItems($stockAdjustment);

        return view('stock-adjustments.edit', [
            'stockAdjustment' => $stockAdjustment,
            'locations' => Location::query()->orderBy('name')->get(),
            'types' => StockAdjustmentType::options(),
            'items' => $items,
        ]);
    }

    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        if (! $stockAdjustment->isReversible()) {
            return redirect()->route('stock-adjustments.index')
                ->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' cannot be updated because stock has moved since it was posted. Create a correction adjustment instead.");
        }

        $original = $stockAdjustment->getOriginal();
        $validated = $request->validated();
        $items = $validated['items'];

        DB::transaction(function () use ($stockAdjustment, $validated, $items) {
            // Return stock to its pre-adjustment level before re-posting.
            StockAdjustmentService::reverse($stockAdjustment);

            $stockAdjustment->update([
                'reference_no' => $validated['reference_no'] ?: $stockAdjustment->reference_no,
                'adjustment_date' => $validated['adjustment_date'],
                'location_id' => $validated['location_id'],
                'type' => $validated['type'],
                'total_amount' => $this->itemsTotal($items),
                'total_amount_recovered' => round((float) ($validated['total_amount_recovered'] ?? 0), 2),
                'reason' => $validated['reason'] ?? null,
            ]);

            StockAdjustmentService::saveItems($stockAdjustment, $items);
            StockAdjustmentService::apply($stockAdjustment);
        });

        AuditService::updated($stockAdjustment, $original, "Stock adjustment '{$stockAdjustment->reference_no}' updated.");

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$stockAdjustment->reference_no}' updated successfully.");
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('delete', $stockAdjustment);

        if (! $stockAdjustment->isReversible()) {
            return back()->with('error', "Stock adjustment '{$stockAdjustment->reference_no}' cannot be deleted because stock has moved since it was posted. Create a correction adjustment instead.");
        }

        DB::transaction(function () use ($stockAdjustment) {
            StockAdjustmentService::reverse($stockAdjustment);
            $stockAdjustment->delete();
        });

        AuditService::deleted($stockAdjustment, "Stock adjustment '{$stockAdjustment->reference_no}' deleted.");

        return redirect()->route('stock-adjustments.index')
            ->with('success', "Stock adjustment '{$stockAdjustment->reference_no}' deleted successfully.");
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
            'location' => 'Location',
            'type' => 'Adjustment type',
            'total_amount' => 'Total Amount',
            'total_amount_recovered' => 'Total amount recovered',
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
            ->with(['location', 'creator'])
            ->when($search !== '', fn ($q) => $q->search($search))
            ->orderBy('adjustment_date', 'desc')
            ->get();

        return [$columns, $adjustments];
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
}
