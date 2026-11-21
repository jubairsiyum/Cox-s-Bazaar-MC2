<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Services\SalesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sales report (reports.sales): invoice-wise listing over a date range with
 * totals and a payment-status breakdown, filterable by branch and location.
 */
class SalesReportController extends Controller
{
    /**
     * Show the sales report.
     */
    public function sales(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $invoices = (clone $query)->orderByDesc('invoice_date')->paginate(50)->withQueryString();

        $sums = (clone $query)->toBase()
            ->selectRaw('coalesce(sum(grand_total), 0) as grand_total, coalesce(sum(credit_amount), 0) as credit_amount, coalesce(sum(paid_amount), 0) as paid_amount, coalesce(sum(due_amount), 0) as due_amount, count(*) as total')
            ->first();

        $paymentBreakdown = (clone $query)->toBase()
            ->selectRaw('payment_status, count(*) as total, coalesce(sum(grand_total), 0) as grand_total, coalesce(sum(paid_amount), 0) as paid_amount, coalesce(sum(due_amount), 0) as due_amount')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        $statusBreakdown = (clone $query)->toBase()
            ->selectRaw('status, count(*) as total, coalesce(sum(grand_total), 0) as grand_total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('reports.sales', [
            'invoices' => $invoices,
            'totals' => [
                'count' => (int) ($sums->total ?? 0),
                'grand_total' => round((float) ($sums->grand_total ?? 0), 2),
                'credit_amount' => round((float) ($sums->credit_amount ?? 0), 2),
                'paid_amount' => round((float) ($sums->paid_amount ?? 0), 2),
                'due_amount' => round((float) ($sums->due_amount ?? 0), 2),
            ],
            'paymentBreakdown' => $paymentBreakdown,
            'statusBreakdown' => $statusBreakdown,
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->active()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Base query with all report filters and the viewer's scope applied.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = SalesInvoice::query()
            ->with(['customer', 'location', 'branch'])
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->string('date_from')->toString()))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->string('date_to')->toString()));

        $query = SalesScope::scopeQuery($query, $request->user());

        return $query
            ->when($request->filled('location_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('location_id', (int) $request->input('location_id')))
            ->when($request->filled('branch_id') && ! SalesScope::isRestricted($request->user()), fn ($q) => $q->where('branch_id', (int) $request->input('branch_id')));
    }
}
