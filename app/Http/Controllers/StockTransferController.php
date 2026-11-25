<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Enums\StockTransferStatus;
use App\Exceptions\InsufficientStockException;
use App\Exports\StockTransferExport;
use App\Helpers\Money;
use App\Http\Requests\StockTransfer\StoreStockTransferRequest;
use App\Http\Requests\StockTransfer\UpdateStockTransferRequest;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\LocationScope;
use App\Services\NotificationService;
use App\Services\PurchaseApprovalNotifier;
use App\Services\StockRequestNotifier;
use App\Services\StockService;
use App\Services\StockTransferService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $query = StockTransfer::query()
            ->with(['fromLocation', 'toLocation'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()));

        // Outlet Managers see the transfers leaving or arriving at their location.
        $ids = LocationScope::accessibleLocationIds($request->user());
        if ($ids !== null) {
            $query->where(fn ($q) => $q->whereIn('from_location_id', $ids)->orWhereIn('to_location_id', $ids));
        }

        // Overview branch filter: matches transfers where either side of the
        // move belongs to the selected branch.
        $query->when($request->filled('branch'), function ($q) use ($request) {
            $branchLocationIds = Location::query()
                ->where('branch_id', $request->integer('branch'))
                ->pluck('id');

            $q->where(fn ($q) => $q->whereIn('from_location_id', $branchLocationIds)->orWhereIn('to_location_id', $branchLocationIds));
        });

        $transfers = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('stock-transfers.index', [
            'transfers' => $transfers,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'branches' => Branch::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new stock transfer.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', StockTransfer::class);

        $stockTransfer = new StockTransfer;

        // Branch-card shortcut: pre-fill the destination branch and default
        // the source to the Main Warehouse, dispatching immediately.
        if ($request->filled('to_location_id')) {
            $stockTransfer->to_location_id = (int) $request->integer('to_location_id');
            $stockTransfer->from_location_id = $this->mainWarehouseId();
            $stockTransfer->status = StockTransferStatus::InTransit;
        }

        return view('stock-transfers.create', [
            'stockTransfer' => $stockTransfer,
            'locations' => $this->scopedLocations(),
            'destinationLocations' => Location::query()->active()->orderBy('name')->get(),
            'statuses' => collect(StockTransferStatus::options())
                ->only(collect(StockTransferStatus::creatable())->map(fn (StockTransferStatus $status) => $status->value)->all())
                ->all(),
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

                StockTransferService::assertAvailableAtSource((int) $validated['from_location_id'], $items);

                $this->saveItems($transfer, $items);

                StockTransferService::applyStatusChange($transfer, null, $transfer->status);

                return $transfer;
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditService::created($transfer, "Stock transfer '{$transfer->reference_no}' created.");

        $this->notifyReceivers(
            $transfer,
            "Stock transfer '{$transfer->reference_no}' awaits your approval.",
            sprintf(
                '%s → %s · %d line(s) · %s, submitted by %s.',
                $transfer->fromLocation?->name ?? 'Unknown location',
                $transfer->toLocation?->name ?? 'Unknown location',
                $transfer->items()->count(),
                Money::format($transfer->total_amount),
                $request->user()?->name ?? 'Unknown user'
            ),
            'warning'
        );

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$transfer->reference_no}' created successfully.");
    }

    /**
     * Show the form for editing the specified stock transfer.
     */
    public function edit(StockTransfer $stockTransfer): View
    {
        $this->authorize('update', $stockTransfer);
        abort_if($stockTransfer->is_request, 403, 'Stock requests are decided by their source and cannot be edited.');

        $itemsEditable = $stockTransfer->status === StockTransferStatus::Draft;

        $items = $itemsEditable && $this->oldItems() !== []
            ? $this->hydrateItems($this->oldItems())
            : $this->presentItems($stockTransfer);

        // "Received" is only reachable through the receiving manager's approval.
        $statuses = collect(StockTransferStatus::transitionsFrom($stockTransfer->status))
            ->reject(fn (StockTransferStatus $status) => $status === StockTransferStatus::Received
                && $stockTransfer->status !== StockTransferStatus::Received)
            ->mapWithKeys(fn (StockTransferStatus $status) => [$status->value => $status->label()])
            ->all();

        return view('stock-transfers.edit', [
            'stockTransfer' => $stockTransfer,
            'locations' => $this->scopedLocations(),
            'destinationLocations' => Location::query()->active()->orderBy('name')->get(),
            'statuses' => $statuses,
            'itemsEditable' => $itemsEditable,
            'items' => $items,
        ]);
    }

    /**
     * Update the specified stock transfer in storage.
     */
    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): RedirectResponse
    {
        abort_if($stockTransfer->is_request, 403, 'Stock requests are decided by their source and cannot be edited.');

        $original = $stockTransfer->getOriginal();
        $validated = $request->validated();
        $shipping = round((float) ($validated['shipping_charges'] ?? 0), 2);
        $fromStatus = StockTransferStatus::from((string) $stockTransfer->getRawOriginal('status'));

        try {
            $fromStatus = DB::transaction(function () use ($stockTransfer, $validated, $shipping) {
                $locked = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->firstOrFail();
                $lockedFromStatus = StockTransferStatus::from((string) $locked->getRawOriginal('status'));
                $itemsEditable = $locked->status === StockTransferStatus::Draft;
                $items = $itemsEditable ? $validated['items'] : [];

                if ($itemsEditable) {
                    StockTransferService::assertAvailableAtSource((int) $validated['from_location_id'], $items);

                    $locked->items()->delete();
                    $this->saveItems($locked, $items);
                }

                $itemsTotal = $itemsEditable
                    ? $this->itemsTotal($items)
                    : (float) $locked->items()->sum('subtotal');

                $locked->update([
                    'reference_no' => $validated['reference_no'] ?: $locked->reference_no,
                    'transfer_date' => $validated['transfer_date'],
                    'status' => $validated['status'],
                    'from_location_id' => $validated['from_location_id'],
                    'to_location_id' => $validated['to_location_id'],
                    'shipping_charges' => $shipping,
                    'total_amount' => $itemsTotal + $shipping,
                    'additional_notes' => $validated['additional_notes'] ?? null,
                ]);

                StockTransferService::applyStatusChange($locked, $lockedFromStatus, $locked->status);

                return $lockedFromStatus;
            });
        } catch (InsufficientStockException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $stockTransfer->refresh();

        AuditService::updated($stockTransfer, $original, "Stock transfer '{$stockTransfer->reference_no}' updated.");

        if ($fromStatus !== $stockTransfer->status) {
            $this->notifyStatusChange($stockTransfer, $fromStatus);
        }

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' updated successfully.");
    }

    /**
     * Remove the specified stock transfer from storage.
     */
    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('delete', $stockTransfer);

        $blockedReason = DB::transaction(function () use ($stockTransfer): ?string {
            $locked = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === StockTransferStatus::Requested) {
                abort(403, 'A pending stock request must be approved or rejected before it can be deleted.');
            }

            if ($locked->status === StockTransferStatus::InTransit) {
                return "Stock transfer '{$locked->reference_no}' is in transit. Cancel it before deleting.";
            }

            if ($locked->status === StockTransferStatus::Received) {
                return "Received stock transfer '{$locked->reference_no}' cannot be deleted because stock has already been posted to the destination.";
            }

            $locked->delete();

            return null;
        });

        if ($blockedReason !== null) {
            return back()->with('error', $blockedReason);
        }

        AuditService::deleted($stockTransfer, "Stock transfer '{$stockTransfer->reference_no}' deleted.");

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' deleted successfully.");
    }

    /**
     * Dispatch a stock transfer or stock request: the source location packs
     * and ships the items. Status moves to InTransit and stock is deducted
     * from the source location, but has not arrived at the destination yet.
     */
    public function dispatch(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('dispatch', $stockTransfer);

        $original = $stockTransfer->getOriginal();

        try {
            $processed = DB::transaction(function () use ($stockTransfer): bool {
                $locked = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, [StockTransferStatus::Draft, StockTransferStatus::Requested], true)) {
                    return false;
                }

                $fromStatus = StockTransferStatus::from((string) $locked->getRawOriginal('status'));

                StockTransferService::assertAvailableAtSource(
                    (int) $locked->from_location_id,
                    $locked->items()->orderBy('id')->get()
                        ->map(fn ($item) => ['unit_id' => $item->unit_id, 'quantity' => $item->quantity])
                        ->values()
                        ->all()
                );

                $locked->update([
                    'status' => StockTransferStatus::InTransit,
                ]);

                StockTransferService::applyStatusChange($locked, $fromStatus, StockTransferStatus::InTransit);

                return true;
            });
        } catch (InsufficientStockException|ValidationException $e) {
            return back()->with('error', $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : $e->getMessage());
        }

        if (! $processed) {
            return back()->with('error', "Stock transfer '{$stockTransfer->reference_no}' cannot be dispatched.");
        }

        $stockTransfer->refresh();

        AuditService::updated($stockTransfer, $original, "Stock transfer '{$stockTransfer->reference_no}' dispatched (In Transit).");

        $this->notifyCreator(
            $stockTransfer,
            "Stock transfer '{$stockTransfer->reference_no}' dispatched.",
            sprintf(
                'Items from %s are now in transit to %s.',
                $stockTransfer->fromLocation?->name ?? 'Source location',
                $stockTransfer->toLocation?->name ?? 'Destination location'
            ),
            'info'
        );

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' has been dispatched and is now In Transit.");
    }

    /**
     * Approve (accept / receive) an incoming transfer: the receiving manager
     * reviews and signs off. Works both on a submitted request (draft) and on
     * a dispatched transfer. Afterwards the transfer reads "Received" for the
     * receiving side and "Delivered" for the sending side.
     */
    public function approve(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('approve', $stockTransfer);

        $original = $stockTransfer->getOriginal();

        try {
            $fromStatus = DB::transaction(function () use ($stockTransfer): ?StockTransferStatus {
                $locked = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, [StockTransferStatus::Draft, StockTransferStatus::InTransit, StockTransferStatus::Requested], true)) {
                    return null;
                }

                $lockedFromStatus = StockTransferStatus::from((string) $locked->getRawOriginal('status'));

                // A draft or a stock request has not dispatched yet, so its stock is still to be taken from the source.
                if (in_array($lockedFromStatus, [StockTransferStatus::Draft, StockTransferStatus::Requested], true)) {
                    StockTransferService::assertAvailableAtSource(
                        (int) $locked->from_location_id,
                        $locked->items()->orderBy('id')->get()
                            ->map(fn ($item) => ['unit_id' => $item->unit_id, 'quantity' => $item->quantity])
                            ->values()
                            ->all()
                    );
                }

                $locked->update([
                    'status' => StockTransferStatus::Received,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);

                StockTransferService::applyStatusChange($locked, $lockedFromStatus, StockTransferStatus::Received);

                return $lockedFromStatus;
            });
        } catch (InsufficientStockException|ValidationException $e) {
            return back()->with('error', $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : $e->getMessage());
        }

        if ($fromStatus === null) {
            return back()->with('error', "Stock transfer '{$stockTransfer->reference_no}' can no longer be approved.");
        }

        $stockTransfer->refresh();

        AuditService::updated($stockTransfer, $original, "Stock transfer '{$stockTransfer->reference_no}' approved and received.");

        if ($fromStatus === StockTransferStatus::Requested) {
            StockRequestNotifier::approved($stockTransfer, Auth::user());

            return redirect()->route('stock-transfers.index')
                ->with('success', "Stock request '{$stockTransfer->reference_no}' approved. The stock has been sent to {$stockTransfer->toLocation?->name}.");
        }

        $this->notifyCreator(
            $stockTransfer,
            "Stock transfer '{$stockTransfer->reference_no}' received.",
            sprintf(
                'Your transfer %s → %s was accepted and received by %s.',
                $stockTransfer->fromLocation?->name ?? 'Unknown location',
                $stockTransfer->toLocation?->name ?? 'Unknown location',
                Auth::user()?->name ?? 'the receiving manager'
            ),
            'success'
        );

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' approved. It now shows as Received for you and Delivered for the sender.");
    }

    /**
     * Reject an incoming transfer: the receiving manager turns the request
     * down. Any dispatched stock returns to the sending location. A stock
     * request is turned down by its source with a reason; nothing has moved
     * for it, so nothing returns.
     */
    public function reject(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('reject', $stockTransfer);

        $reason = $stockTransfer->is_request
            ? $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']
            : null;

        $original = $stockTransfer->getOriginal();

        $processed = DB::transaction(function () use ($stockTransfer, $reason): bool {
            $locked = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [StockTransferStatus::Draft, StockTransferStatus::InTransit, StockTransferStatus::Requested], true)) {
                return false;
            }

            $fromStatus = StockTransferStatus::from((string) $locked->getRawOriginal('status'));

            $locked->update([
                'status' => StockTransferStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            StockTransferService::applyStatusChange($locked, $fromStatus, StockTransferStatus::Rejected);

            return true;
        });

        if (! $processed) {
            return back()->with('error', "Stock transfer '{$stockTransfer->reference_no}' can no longer be rejected.");
        }

        $stockTransfer->refresh();

        AuditService::updated($stockTransfer, $original, "Stock transfer '{$stockTransfer->reference_no}' rejected.");

        if ($stockTransfer->is_request) {
            StockRequestNotifier::rejected($stockTransfer, Auth::user(), $reason);

            return redirect()->route('stock-transfers.index')
                ->with('success', "Stock request '{$stockTransfer->reference_no}' rejected.");
        }

        $this->notifyCreator(
            $stockTransfer,
            "Stock transfer '{$stockTransfer->reference_no}' rejected.",
            sprintf(
                'Your transfer %s → %s was rejected by %s. Any dispatched stock has been returned.',
                $stockTransfer->fromLocation?->name ?? 'Unknown location',
                $stockTransfer->toLocation?->name ?? 'Unknown location',
                Auth::user()?->name ?? 'the receiving manager'
            ),
            'error'
        );

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' rejected.");
    }

    /**
     * Withdraw a pending stock request: the requesting branch calls it back
     * before the source decides. No stock has moved, so nothing is reversed.
     */
    public function withdraw(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('withdraw', $stockTransfer);
        abort_unless((int) $stockTransfer->created_by === (int) $request->user()?->id, 403);

        $original = $stockTransfer->getOriginal();

        $stockTransfer->update(['status' => StockTransferStatus::Cancelled]);

        AuditService::updated($stockTransfer, $original, "Stock request '{$stockTransfer->reference_no}' withdrawn by the requesting branch.");

        StockRequestNotifier::withdrawn($stockTransfer, $request->user());

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock request '{$stockTransfer->reference_no}' withdrawn.");
    }

    /**
     * Cancel a transfer: the sending manager calls off a pending transfer
     * before it is received. Any dispatched stock returns to the source.
     */
    public function cancel(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->authorize('cancel', $stockTransfer);

        $original = $stockTransfer->getOriginal();

        $processed = DB::transaction(function () use ($stockTransfer): bool {
            $locked = StockTransfer::query()->whereKey($stockTransfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [StockTransferStatus::Draft, StockTransferStatus::InTransit], true)) {
                return false;
            }

            $fromStatus = StockTransferStatus::from((string) $locked->getRawOriginal('status'));

            $locked->update(['status' => StockTransferStatus::Cancelled]);

            StockTransferService::applyStatusChange($locked, $fromStatus, StockTransferStatus::Cancelled);

            return true;
        });

        if (! $processed) {
            return back()->with('error', "Stock transfer '{$stockTransfer->reference_no}' can no longer be cancelled.");
        }

        $stockTransfer->refresh();

        AuditService::updated($stockTransfer, $original, "Stock transfer '{$stockTransfer->reference_no}' cancelled by the sending side.");

        $this->notifyReceivers(
            $stockTransfer,
            "Stock transfer '{$stockTransfer->reference_no}' cancelled.",
            sprintf(
                'The transfer %s → %s was cancelled by %s.',
                $stockTransfer->fromLocation?->name ?? 'Unknown location',
                $stockTransfer->toLocation?->name ?? 'Unknown location',
                Auth::user()?->name ?? 'the sending manager'
            ),
            'info'
        );

        return redirect()->route('stock-transfers.index')
            ->with('success', "Stock transfer '{$stockTransfer->reference_no}' cancelled.");
    }

    /**
     * Display the transfer as an individual printable invoice.
     */
    public function show(StockTransfer $stockTransfer): View
    {
        $this->authorize('view', $stockTransfer);

        $stockTransfer->load(['items.unit.product', 'fromLocation', 'toLocation', 'creator', 'approver']);

        return view('stock-transfers.show', [
            'transfer' => $stockTransfer,
        ]);
    }

    /**
     * Search stock lines (products) for the transfer form. Quantities shown
     * are scoped to the acting user's own branch (strict branch isolation).
     */
    public function searchUnits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

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
            new StockTransferExport($search ?: null, $columns ?: null, $request->user()),
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
     * Resolve the Main Warehouse used as the default dispatch source.
     *
     * Prefers the shared {@see Location::mainWarehouse()} helper once it is
     * available and otherwise falls back to the first warehouse (giving the
     * "Main Warehouse" name precedence).
     */
    private function mainWarehouseId(): ?int
    {
        if (method_exists(Location::class, 'mainWarehouse')) {
            return Location::mainWarehouse()?->id;
        }

        $id = Location::query()
            ->where('type', LocationType::Warehouse)
            ->orderByRaw("CASE WHEN name = 'Main Warehouse' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Locations the current user may transfer stock from.
     *
     * @return Collection<int, Location>
     */
    private function scopedLocations(): Collection
    {
        $query = Location::query()->active()->orderBy('name');

        $ids = LocationScope::accessibleLocationIds(Auth::user());

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get();
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

        $stocks = StockService::scopedQuantitiesFor($units, Auth::user());

        return collect($rows)->values()->map(function (array $row, int $index) use ($units, $stocks) {
            $unit = $units->get((int) ($row['unit_id'] ?? 0));

            return [
                'unit_id' => (int) ($row['unit_id'] ?? 0),
                'product_name' => $unit?->displayName() ?? 'Unknown product',
                'unit' => $unit?->unit ?? '',
                'stock' => $unit ? ($stocks[$unit->id] ?? 0) : null,
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'error' => session('errors')?->first("items.{$index}.quantity"),
            ];
        })->all();
    }

    /**
     * Line items of an existing transfer shaped for the form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentItems(StockTransfer $stockTransfer): array
    {
        $stocks = StockService::scopedQuantitiesFor($stockTransfer->items->pluck('unit')->filter(), Auth::user());

        return $stockTransfer->items->map(fn ($item) => [
            'unit_id' => (int) $item->unit_id,
            'product_name' => $item->unit?->displayName() ?? 'Unknown product',
            'unit' => $item->unit?->unit ?? '',
            'stock' => $stocks[(int) $item->unit_id] ?? null,
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

    /**
     * Notify the managers of the receiving location/branch about a transfer event.
     */
    private function notifyReceivers(StockTransfer $transfer, string $title, string $body, string $type): void
    {
        NotificationService::notifyMany(
            PurchaseApprovalNotifier::managersFor($transfer->to_location_id),
            $title,
            $body,
            route('stock-transfers.show', $transfer),
            $type
        );
    }

    /**
     * Notify the creator of a transfer about a decision taken on it.
     */
    private function notifyCreator(StockTransfer $transfer, string $title, string $body, string $type): void
    {
        if ($transfer->creator === null) {
            return;
        }

        NotificationService::notify($transfer->creator, $title, $body, route('stock-transfers.show', $transfer), $type);
    }

    /**
     * Tell the receiving side when a transfer they were awaiting changes state.
     */
    private function notifyStatusChange(StockTransfer $transfer, StockTransferStatus $from): void
    {
        if ($transfer->status === StockTransferStatus::InTransit && $from === StockTransferStatus::Draft) {
            $this->notifyReceivers(
                $transfer,
                "Stock transfer '{$transfer->reference_no}' dispatched.",
                sprintf(
                    'The transfer %s → %s is on the way and awaits your receipt sign-off.',
                    $transfer->fromLocation?->name ?? 'Unknown location',
                    $transfer->toLocation?->name ?? 'Unknown location'
                ),
                'warning'
            );
        }

        if ($transfer->status === StockTransferStatus::Cancelled) {
            $this->notifyReceivers(
                $transfer,
                "Stock transfer '{$transfer->reference_no}' cancelled.",
                sprintf(
                    'The transfer %s → %s was cancelled by %s.',
                    $transfer->fromLocation?->name ?? 'Unknown location',
                    $transfer->toLocation?->name ?? 'Unknown location',
                    Auth::user()?->name ?? 'the sending manager'
                ),
                'info'
            );
        }
    }
}
