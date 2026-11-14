<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\SupplierExport;
use App\Http\Requests\Supplier\ImportSupplierRequest;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Imports\SupplierImport;
use App\Models\Supplier;
use App\Models\SupplierOpeningPayment;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $query = Supplier::query()
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id');

        // Total calculations over the filtered query
        $totalPurchaseDue = (clone $query)->sum('purchase_due');
        $totalPurchaseReturnDue = (clone $query)->sum('purchase_return_due');

        $suppliers = $query->paginate($perPage)->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'totalPurchaseDue' => $totalPurchaseDue,
            'totalPurchaseReturnDue' => $totalPurchaseReturnDue,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Show the form for creating a new supplier (or redirect to edit if supplier ID is provided).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        if ($request->filled('supplier_id') || $request->filled('id')) {
            $identifier = $request->input('supplier_id') ?: $request->input('id');
            $supplier = Supplier::where('id', $identifier)
                ->orWhere('supplier_id', $identifier)
                ->first();

            if ($supplier) {
                return redirect()->route('suppliers.edit', $supplier);
            }
        }

        return view('suppliers.create', [
            'supplier' => new Supplier,
            'openingPayments' => collect(),
        ]);
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        // If opening balance is entered and purchase_due is not specified, set purchase_due to opening balance
        if (isset($validated['opening_balance']) && ! isset($validated['purchase_due'])) {
            $validated['purchase_due'] = $validated['opening_balance'];
        }

        $supplier = Supplier::create($validated);

        AuditService::created($supplier, "Supplier '{$supplier->name}' ({$supplier->supplier_id}) created.");

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        $openingPayments = $supplier->openingPayments()->latest()->get();

        return view('suppliers.edit', [
            'supplier' => $supplier,
            'openingPayments' => $openingPayments,
        ]);
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $original = $supplier->getOriginal();
        $validated = $request->validated();

        $supplier->update($validated);

        AuditService::updated($supplier, $original, "Supplier '{$supplier->name}' ({$supplier->supplier_id}) updated.");

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' updated successfully.");
    }

    /**
     * Remove the specified supplier from storage.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        AuditService::deleted($supplier, "Supplier '{$supplier->name}' ({$supplier->supplier_id}) deleted.");

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' deleted successfully.");
    }

    /**
     * Toggle active/inactive status of the specified supplier.
     */
    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $this->authorize('toggleStatus', $supplier);

        $oldStatus = $supplier->status;
        $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';

        $supplier->update(['status' => $newStatus]);

        AuditService::log(
            'updated',
            $supplier,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Supplier '{$supplier->name}' ({$supplier->supplier_id}) status changed to {$newStatus}."
        );

        return back()->with('success', "Supplier '{$supplier->name}' status updated to ".ucfirst($newStatus).'.');
    }

    /**
     * Record a payment towards the supplier's purchase due (opening balance or purchase due).
     */
    public function payDue(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_type' => ['required', 'string', 'max:50'],
            'payment_note' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $validated['amount'];

        $payment = $supplier->openingPayments()->create([
            'payment_date' => $validated['payment_date'],
            'amount' => $amount,
            'payment_type' => $validated['payment_type'],
            'payment_note' => $validated['payment_note'] ?? 'Due Payment',
            'created_by' => $request->user()->id,
        ]);

        $newDue = max(0, (float) $supplier->purchase_due - $amount);
        $supplier->update(['purchase_due' => $newDue]);

        AuditService::log('paid', $payment, [], $validated, "Recorded payment of {$amount} for supplier '{$supplier->name}'.");

        return back()->with('success', 'Payment of '.number_format($amount, 2).' recorded successfully.');
    }

    /**
     * Record a payment towards the supplier's purchase return due.
     */
    public function payReturnDue(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_type' => ['required', 'string', 'max:50'],
            'payment_note' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $validated['amount'];

        $payment = $supplier->openingPayments()->create([
            'payment_date' => $validated['payment_date'],
            'amount' => $amount,
            'payment_type' => $validated['payment_type'],
            'payment_note' => $validated['payment_note'] ?? 'Return Due Payment',
            'created_by' => $request->user()->id,
        ]);

        $newDue = max(0, (float) $supplier->purchase_return_due - $amount);
        $supplier->update(['purchase_return_due' => $newDue]);

        AuditService::log('paid', $payment, [], $validated, "Recorded return payment of {$amount} for supplier '{$supplier->name}'.");

        return back()->with('success', 'Return payment of '.number_format($amount, 2).' recorded successfully.');
    }

    /**
     * Delete a payment record and restore due.
     */
    public function destroyPayment(SupplierOpeningPayment $payment): RedirectResponse
    {
        $supplier = $payment->supplier;
        $this->authorize('update', $supplier);

        $amount = (float) $payment->amount;
        $supplier->increment('purchase_due', $amount);

        $payment->delete();

        AuditService::log('deleted', $payment, [], [], "Reverted payment of {$amount} for supplier '{$supplier->name}'.");

        return back()->with('success', 'Payment deleted and due balance updated.');
    }

    /**
     * Show the import suppliers view.
     */
    public function importView(): View
    {
        $this->authorize('import', Supplier::class);

        return view('suppliers.import');
    }

    /**
     * Handle supplier import from CSV / Excel.
     */
    public function import(ImportSupplierRequest $request): RedirectResponse
    {
        $import = new SupplierImport;
        Excel::import($import, $request->file('file'));

        $count = $import->getImportedCount();

        AuditService::log('imported', null, [], ['count' => $count], "Imported {$count} suppliers from file.");

        return redirect()->route('suppliers.index')
            ->with('success', "Successfully imported {$count} supplier(s).");
    }

    /**
     * Download example CSV template for supplier import.
     */
    public function downloadTemplate(): Response
    {
        $this->authorize('viewAny', Supplier::class);

        $headers = [
            'Supplier Name',
            'Mobile',
            'Email',
            'Phone',
            'GST Number',
            'TAX Number',
            'Country Name',
            'State Name',
            'City',
            'Postcode',
            'Address',
            'Opening Balance',
        ];

        $sampleRow = [
            'Subhramaniam',
            '98978787877',
            'subhramaniam@gmail.com',
            '023456789',
            'GSTIN12345',
            'TAX98765',
            'Bangladesh',
            'Chittagong',
            "Cox's Bazar",
            '4700',
            'Main Road, Cox\'s Bazar',
            '3300.00',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $sampleRow);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="supplier_import_template.csv"',
        ]);
    }

    /**
     * Export suppliers to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $filename = 'suppliers_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(new SupplierExport($search ?: null, $status ?: null), $filename);
    }
}
