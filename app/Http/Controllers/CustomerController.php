<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\CustomerExport;
use App\Http\Requests\Customer\ImportCustomerRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Imports\CustomerImport;
use App\Models\Customer;
use App\Models\CustomerCustomField;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $query = Customer::query()
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id');

        $customers = $query->paginate($perPage)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Show the form for creating a new customer (or redirect to edit if customer ID is provided).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        if ($request->filled('customer_id') || $request->filled('id')) {
            $identifier = $request->input('customer_id') ?: $request->input('id');
            $customer = Customer::where('id', $identifier)
                ->orWhere('customer_id', $identifier)
                ->first();

            if ($customer) {
                return redirect()->route('customers.edit', $customer);
            }
        }

        $customFieldDefinitions = CustomerCustomField::orderBy('id')->get();

        return view('customers.create', [
            'customer' => new Customer,
            'customFieldDefinitions' => $customFieldDefinitions,
        ]);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        // Filter empty custom field rows and sync into customer_custom_fields table
        if (isset($validated['custom_fields']) && is_array($validated['custom_fields'])) {
            $validated['custom_fields'] = array_values(array_filter($validated['custom_fields'], function ($field) use ($request) {
                $hasLabel = ! empty($field['label']);
                $hasValue = isset($field['value']) && $field['value'] !== '';

                if ($hasLabel) {
                    CustomerCustomField::firstOrCreate(
                        ['name' => trim((string) $field['label'])],
                        ['created_by' => $request->user()?->id]
                    );
                }

                return $hasLabel && $hasValue;
            }));

            if (empty($validated['custom_fields'])) {
                $validated['custom_fields'] = null;
            }
        }

        $customer = Customer::create($validated);

        AuditService::created($customer, "Customer '{$customer->name}' ({$customer->customer_id}) created.");

        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customer->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        $customFieldDefinitions = CustomerCustomField::orderBy('id')->get();

        return view('customers.edit', [
            'customer' => $customer,
            'customFieldDefinitions' => $customFieldDefinitions,
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $original = $customer->getOriginal();
        $validated = $request->validated();

        // Filter empty custom field rows and sync into customer_custom_fields table
        if (isset($validated['custom_fields']) && is_array($validated['custom_fields'])) {
            $validated['custom_fields'] = array_values(array_filter($validated['custom_fields'], function ($field) use ($request) {
                $hasLabel = ! empty($field['label']);
                $hasValue = isset($field['value']) && $field['value'] !== '';

                if ($hasLabel) {
                    CustomerCustomField::firstOrCreate(
                        ['name' => trim((string) $field['label'])],
                        ['created_by' => $request->user()?->id]
                    );
                }

                return $hasLabel && $hasValue;
            }));

            if (empty($validated['custom_fields'])) {
                $validated['custom_fields'] = null;
            }
        }

        $customer->update($validated);

        AuditService::updated($customer, $original, "Customer '{$customer->name}' ({$customer->customer_id}) updated.");

        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customer->name}' updated successfully.");
    }

    /**
     * Store a new custom field definition in DB dynamically.
     */
    public function storeCustomField(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($validated['name']);
        $field = CustomerCustomField::firstOrCreate(
            ['name' => $name],
            ['created_by' => $request->user()?->id]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'custom_field' => $field,
            ]);
        }

        return back()->with('success', "Custom field '{$field->name}' added successfully.");
    }

    /**
     * Delete a custom field definition from DB.
     */
    public function destroyCustomField(Request $request, CustomerCustomField $customField): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $name = $customField->name;
        $customField->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return back()->with('success', "Custom field '{$name}' deleted successfully.");
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        AuditService::deleted($customer, "Customer '{$customer->name}' ({$customer->customer_id}) deleted.");

        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customer->name}' deleted successfully.");
    }

    /**
     * Toggle active/inactive status of the specified customer.
     */
    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->authorize('toggleStatus', $customer);

        $oldStatus = $customer->status;
        $newStatus = $customer->status === 'active' ? 'inactive' : 'active';

        $customer->update(['status' => $newStatus]);

        AuditService::log(
            'updated',
            $customer,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Customer '{$customer->name}' ({$customer->customer_id}) status changed to {$newStatus}."
        );

        return back()->with('success', "Customer '{$customer->name}' status updated to ".ucfirst($newStatus).'.');
    }

    /**
     * Show the import customers view.
     */
    public function importView(): View
    {
        $this->authorize('import', Customer::class);

        return view('customers.import');
    }

    /**
     * Handle customer import from CSV / Excel.
     */
    public function import(ImportCustomerRequest $request): RedirectResponse
    {
        $import = new CustomerImport;
        Excel::import($import, $request->file('file'));

        $count = $import->getImportedCount();

        AuditService::log('imported', null, [], ['count' => $count], "Imported {$count} customers from file.");

        return redirect()->route('customers.index')
            ->with('success', "Successfully imported {$count} customer(s).");
    }

    /**
     * Download example CSV template for customer import.
     */
    public function downloadTemplate(): Response
    {
        $this->authorize('viewAny', Customer::class);

        $headers = [
            'Customer Name',
            'Mobile',
            'Phone',
            'Email',
            'Country Name',
            'State Name',
            'City',
            'Postcode',
            'Address',
            'Note',
        ];

        $sampleRow = [
            'Rahim Uddin',
            '01712345678',
            '034164100',
            'rahim@example.com',
            'Bangladesh',
            'Chittagong',
            "Cox's Bazar",
            '4700',
            'Main Road, Cox\'s Bazar',
            'Regular retail customer',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $sampleRow);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customer_import_template.csv"',
        ]);
    }

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $columnsParam = $request->string('columns')->toString();

        $defaultColumns = [
            'customer_id' => 'Customer ID',
            'name' => 'Customer Name',
            'mobile' => 'Mobile',
            'phone' => 'Phone',
            'email' => 'Email',
            'city' => 'City',
            'status' => 'Status',
        ];

        if ($columnsParam !== '') {
            $requested = array_filter(array_map('trim', explode(',', $columnsParam)));
            $columns = [];
            foreach ($requested as $col) {
                if (isset($defaultColumns[$col])) {
                    $columns[$col] = $defaultColumns[$col];
                }
            }
            if (empty($columns)) {
                $columns = $defaultColumns;
            }
        } else {
            $columns = $defaultColumns;
        }

        $customers = Customer::query()
            ->when($search !== '', fn ($q) => $q->search($search))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get();

        return view('customers.pdf', [
            'customers' => $customers,
            'columns' => $columns,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Show clean print layout matching the reference image.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $columnsParam = $request->string('columns')->toString();

        $defaultColumns = [
            'customer_id' => 'Customer ID',
            'name' => 'Customer Name',
            'mobile' => 'Mobile',
            'phone' => 'Phone',
            'email' => 'Email',
            'city' => 'City',
            'status' => 'Status',
        ];

        if ($columnsParam !== '') {
            $requested = array_filter(array_map('trim', explode(',', $columnsParam)));
            $columns = [];
            foreach ($requested as $col) {
                if (isset($defaultColumns[$col])) {
                    $columns[$col] = $defaultColumns[$col];
                }
            }
            if (empty($columns)) {
                $columns = $defaultColumns;
            }
        } else {
            $columns = $defaultColumns;
        }

        $customers = Customer::query()
            ->when($search !== '', fn ($q) => $q->search($search))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get();

        return view('customers.print', [
            'customers' => $customers,
            'columns' => $columns,
            'search' => $search,
            'status' => $status,
        ]);
    }

    /**
     * Export customers to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', Customer::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'customers_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new CustomerExport($search ?: null, $status ?: null, $columns ?: null),
            $filename
        );
    }
}
