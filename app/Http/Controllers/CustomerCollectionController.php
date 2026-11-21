<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesInvoiceStatus;
use App\Http\Requests\CustomerCollection\StoreCustomerCollectionRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CustomerCollectionService;
use App\Services\SalesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerCollectionController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['collection_date', 'collection_no', 'amount', 'method'];

    /**
     * Display the collections register.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerCollection::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'collection_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';

        $collections = $this->filteredQuery($request)
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('customer-collections.index', [
            'collections' => $collections,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'totals' => $this->totals($request),
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show the payment recording form, optionally prefilled for a customer or
     * a specific invoice ("Record Payment" from an invoice page).
     */
    public function create(Request $request): View
    {
        $this->authorize('create', CustomerCollection::class);

        $customerId = (int) (old('customer_id') ?: $request->input('customer')) ?: null;
        $invoice = SalesInvoice::query()->find($request->input('invoice'));

        return view('customer-collections.create', [
            'customers' => Customer::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'invoices' => $this->openInvoicesList($customerId, Auth::user()),
            'preselectedInvoiceId' => $invoice !== null && (int) $invoice->customer_id === $customerId ? $invoice->id : null,
            'defaultLocationId' => old('location_id') ?: Auth::user()?->branch_id,
        ]);
    }

    /**
     * Store a collection and allocate it to the chosen invoices.
     */
    public function store(StoreCustomerCollectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! SalesScope::canAccess($request->user(), $validated['location_id'])) {
            return back()->withInput()->with('error', 'You can only record collections for your own branch/location.');
        }

        $collection = DB::transaction(function () use ($request, $validated) {
            $collection = CustomerCollection::create([
                'customer_id' => $validated['customer_id'],
                'collection_date' => $validated['collection_date'],
                'amount' => (float) $validated['amount'],
                'method' => $validated['method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'note' => $validated['note'] ?? null,
                'location_id' => $validated['location_id'],
                // The creator's branch: the branch of their assigned outlet.
                'branch_id' => $request->user()?->branch?->branch_id,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($validated['allocations'] as $row) {
                $collection->allocations()->create([
                    'sales_invoice_id' => (int) $row['sales_invoice_id'],
                    'amount' => (float) $row['amount'],
                ]);
            }

            CustomerCollectionService::applyAllocations($collection);

            return $collection;
        });

        AuditService::created($collection, "Collection '{$collection->collection_no}' recorded.");

        return redirect()->route('customer-collections.show', $collection)
            ->with('success', "Collection '{$collection->collection_no}' recorded successfully.");
    }

    /**
     * Display the specified collection.
     */
    public function show(CustomerCollection $customerCollection): View
    {
        $this->authorize('view', $customerCollection);

        $customerCollection->load(['allocations.salesInvoice', 'customer', 'location', 'branch', 'creator']);

        return view('customer-collections.show', [
            'collection' => $customerCollection,
        ]);
    }

    /**
     * Show the printable payment receipt.
     */
    public function receipt(CustomerCollection $customerCollection): View
    {
        $this->authorize('view', $customerCollection);

        $customerCollection->load(['allocations.salesInvoice', 'customer', 'location', 'branch', 'creator']);

        return view('customer-collections.receipt', [
            'collection' => $customerCollection,
        ]);
    }

    /**
     * Delete a collection and reverse its allocations.
     */
    public function destroy(CustomerCollection $customerCollection): RedirectResponse
    {
        $this->authorize('delete', $customerCollection);

        DB::transaction(function () use ($customerCollection) {
            CustomerCollectionService::releaseAllocations($customerCollection);
            $customerCollection->allocations()->delete();
            $customerCollection->delete();
        });

        AuditService::deleted($customerCollection, "Collection '{$customerCollection->collection_no}' deleted.");

        return redirect()->route('customer-collections.index')
            ->with('success', "Collection '{$customerCollection->collection_no}' deleted and invoice payments reversed.");
    }

    /**
     * Open (posted) invoices of a customer with their due amounts, for the
     * payment form's allocation grid.
     */
    public function customerInvoices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerCollection::class);

        return response()->json([
            'success' => true,
            'invoices' => $this->openInvoicesList((int) $request->input('customer_id') ?: null, $request->user()),
        ]);
    }

    /**
     * Open invoices of a customer shaped for the allocation grid.
     * Respects the viewer's branch/location scope.
     *
     * @return array<int, array<string, mixed>>
     */
    private function openInvoicesList(?int $customerId, ?User $user = null): array
    {
        if (! $customerId) {
            return [];
        }

        $query = SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->where('status', SalesInvoiceStatus::Posted)
            ->where('due_amount', '>', 0)
            ->orderByDesc('invoice_date')
            ->limit(50);

        if ($user !== null) {
            $query = SalesScope::scopeQuery($query, $user);
        }

        return $query->get()
            ->map(fn (SalesInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->format('d/m/Y H:i'),
                'grand_total' => (float) $invoice->grand_total,
                'paid_amount' => (float) $invoice->paid_amount,
                'payment_due' => (float) $invoice->payment_due,
            ])
            ->all();
    }

    /**
     * Base query for the register with all filters applied.
     * Outlet Managers only see collections of their branch/location.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = CustomerCollection::query()
            ->with(['customer', 'location', 'branch'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('collection_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('collection_date', '<=', $request->string('date_to')->toString()));

        $query = SalesScope::scopeQuery($query, $request->user());

        return $query
            ->when($request->filled('location_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('location_id', (int) $request->input('location_id')))
            ->when($request->filled('branch_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')));
    }

    /**
     * Summary numbers for the register cards and table footer.
     *
     * @return array{total: int, amount: float}
     */
    private function totals(Request $request): array
    {
        $base = $this->filteredQuery($request);

        return [
            'total' => (int) (clone $base)->toBase()->count(),
            'amount' => round((float) (clone $base)->toBase()->sum('amount'), 2),
        ];
    }
}
