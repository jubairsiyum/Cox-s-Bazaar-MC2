<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockTransferStatus;
use App\Exceptions\InsufficientStockException;
use App\Exports\StockTransferExport;
use App\Http\Requests\StockTransfer\StoreStockTransferRequest;
use App\Http\Requests\StockTransfer\UpdateStockTransferRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\StockTransferService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockTransferController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['reference_no', 'transfer_date', 'status', 'shipping_charges', 'total_amount', 'additional_notes'];

    /**
     * Display a listing of stock transfers.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'transfer_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $transfers = StockTransfer::query()
            ->with(['fromLocation', 'toLocation'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('stock-transfers.index', [
            'transfers' => $transfers,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Show the form for creating a new stock transfer.
     */
    public function create(): View
    {
        $this->authorize('create', StockTransfer::class);

        return view('stock-transfers.create', [
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'statuses' => StockTransferStatus::options(),
            'items' => $this->hydrateItems(collect(old('items', []))->all()),
        ]);
    }

    /**
     * Store a newly created stock transfer in storage.
     */
    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'];
        $shipping = round((float) ($validated['shipping_charges'] ?? 0), 2);

        try {
            $transfer = DB::transaction(function () use ($request, $validated, $items, $shipping) {
                $transfer = StockTransfer::create([
                    'reference_no' => $validated['reference_no'] ?? null,
                    'transfer_date' => $validated['transfer_date'],
                    'status' => $validated['status'],
                    'from_location_id' => $validated['from_location_id'],
                    'to_location_id' => $validated['to_location_id'],
                    'shipping_charges' => $shipping,
                    'total_amount' => $this->itemsTotal($items) + $shipping,
                    'additional_notes' => $validated['additional_notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $this->saveItems($transfer, $items);

                StockTransferService::applyStatusChange($transfer, null, $transfer->status);

                return $transfer;
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::created($transfer, "Stock transfer '{$transfer->reference_no}' created.");

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$transfer->reference_no}' created successfully.");
    }

    /**
     * Show the form for editing the specified stock transfer.
     */
    public function edit(StockTransfer $stockTransfer): View
    {
        $this->authorize('update', $stockTransfer);

        $itemsEditable = $stockTransfer->status === StockTransferStatus::Draft;

        $items = $itemsEditable && $this->oldItems() !== []
            ? $this->hydrateItems($this->oldItems())
            : $this->presentItems($stockTransfer);

        return view('stock-transfers.edit', [
            'stockTransfer' => $stockTransfer,
            'locations' => Location::query()->orderBy('name')->get(),
            'statuses' => collect(StockTransferStatus::transitionsFrom($stockTransfer->status))
                ->mapWithKeys(fn (StockTransferStatus $status) => [$status->value => $status->label()])
                ->all(),
            'itemsEditable' => $itemsEditable,
            'items' => $items,
        ]);
    }

    /**
     * Update the specified stock transfer in storage.
     */
    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $original = $stockTransfer->getOriginal();
        $fromStatus = StockTransferStatus::from((string) $stockTransfer->getRawOriginal('status'));
        $validated = $request->validated();
        $itemsEditable = $stockTransfer->status === StockTransferStatus::Draft;
        $items = $itemsEditable ? $validated['items'] : [];
        $shipping = round((float) ($validated['shipping_charges'] ?? 0), 2);

        try {
            DB::transaction(function () use ($stockTransfer, $validated, $items, $itemsEditable, $shipping, $fromStatus) {
                if ($itemsEditable) {
                    $stockTransfer->items()->delete();
                    $this->saveItems($stockTransfer, $items);
                }

                $itemsTotal = $itemsEditable
                    ? $this->itemsTotal($items)
                    : (float) $stockTransfer->items()->sum('subtotal');

                $stockTransfer->update([
                    'reference_no' => $validated['reference_no'] ?: $stockTransfer->reference_no,
                    'transfer_date' => $validated['transfer_date'],
                    'status' => $validated['status'],
                    'from_location_id' => $validated['from_location_id'],
                    'to_location_id' => $validated['to_location_id'],
                    'shipping_charges' => $shipping,
                    'total_amount' => $itemsTotal + $shipping,
                    'additional_notes' => $validated['additional_notes'] ?? null,
                ]);

                StockTransferService::applyStatusChange($stockTransfer, $fromStatus, $stockTransfer->status);
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::updated($stockTransfer, $original, "Stock transfer '{$stockTransfer->reference_no}' updated.");

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' updated successfully.");
    }

    /**
     * Remove the specified stock transfer from storage.
     */
    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('delete', $stockTransfer);

        if ($stockTransfer->status === StockTransferStatus::InTransit) {
            return back()->with('error', "Stock transfer '{$stockTransfer->reference_no}' is in transit. Cancel it before deleting.");
        }

        $stockTransfer->delete();

        AuditService::deleted($stockTransfer, "Stock transfer '{$stockTransfer->reference_no}' deleted.");

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' deleted successfully.");
    }

    /**
     * Search stock lines (products) for the transfer form.
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

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

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        [$columns, $transfers] = $this->exportData($request);

        return view('stock-transfers.pdf', [
            'transfers' => $transfers,
            'columns' => $columns,
        ]);
    }

    /**
     * Show clean print layout matching the reference layout.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', StockTransfer::class);

        [$columns, $transfers] = $this->exportData($request);

        return view('stock-transfers.print', [
            'transfers' => $transfers,
            'columns' => $columns,
        ]);
    }

    /**
     * Export stock transfers to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $search = $request->string('search')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'stock_transfers_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new StockTransferExport($search ?: null, $columns ?: null),
            $filename
        );
    }

    /**
     * Resolve the visible columns and matching transfers for PDF / print views.
     *
     * @return array{0: array<string, string>, 1: Collection<int, StockTransfer>}
     */
    private function exportData(Request $request): array
    {
        $search = $request->string('search')->toString();
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'transfer_date' => 'Date',
            'reference_no' => 'Reference No',
            'from_location' => 'Location (From)',
            'to_location' => 'Location (To)',
            'status' => 'Status',
            'shipping_charges' => 'Shipping Charges',
            'total_amount' => 'Total Amount',
            'additional_notes' => 'Additional Notes',
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

        $transfers = StockTransfer::query()
            ->with(['fromLocation', 'toLocation'])
            ->when($search !== '', fn ($q) => $q->search($search))
            ->orderBy('transfer_date', 'desc')
            ->get();

        return [$columns, $transfers];
    }

    /**
     * Old line items submitted before a validation redirect.
     *
     * @return array<int, array<string, mixed>>
     */
    private function oldItems(): array
    {
        return collect(old('items', []))->all();
    }

    /**
     * Resolve product names for line item rows.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateItems(array $rows): array
    {
        $units = Unit::query()
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
     * Line items of an existing transfer shaped for the form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentItems(StockTransfer $stockTransfer): array
    {
        return $stockTransfer->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'unit' => $item->unit?->unit ?? '',
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
        ])->values()->all();
    }

    /**
     * Persist the line items of a transfer with computed subtotals.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function saveItems(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = round((float) $item['unit_price'], 2);

            $transfer->items()->create([
                'unit_id' => (int) $item['unit_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($quantity * $unitPrice, 2),
            ]);
        }
    }

    /**
     * Sum of quantity × unit price across line items.
     *
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
