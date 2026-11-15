<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\UnitExport;
use App\Http\Requests\Unit\ImportUnitRequest;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Imports\UnitImport;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UnitController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['product_name', 'quantity', 'unit', 'note'];

    /**
     * Display a listing of quantity entries.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'product_name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $units = Unit::query()
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('units.index', [
            'units' => $units,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Store a newly created quantity entry in storage.
     */
    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $unit = Unit::create($validated);

        AuditService::created($unit, "Quantity '{$unit->quantity} {$unit->unit}' for '{$unit->product_name}' created.");

        return redirect()->route('units.index')
            ->with('success', "Quantity for '{$unit->product_name}' created successfully.");
    }

    /**
     * Update the specified quantity entry in storage.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $original = $unit->getOriginal();
        $validated = $request->validated();

        // Quantity changes go through the stock ledger; metadata is saved below.
        StockService::adjust($unit, (int) $validated['quantity']);
        unset($validated['quantity']);

        $unit->update($validated);
        $unit->refresh();

        AuditService::updated($unit, $original, "Quantity '{$unit->quantity} {$unit->unit}' for '{$unit->product_name}' updated.");

        return redirect()->route('units.index')
            ->with('success', "Quantity for '{$unit->product_name}' updated successfully.");
    }

    /**
     * Remove the specified quantity entry from storage.
     */
    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        AuditService::deleted($unit, "Quantity '{$unit->quantity} {$unit->unit}' for '{$unit->product_name}' deleted.");

        return redirect()->route('units.index')
            ->with('success', "Quantity for '{$unit->product_name}' deleted successfully.");
    }

    /**
     * Show the import quantity entries view.
     */
    public function importView(): View
    {
        $this->authorize('import', Unit::class);

        return view('units.import');
    }

    /**
     * Handle quantity entry import from CSV / Excel.
     */
    public function import(ImportUnitRequest $request): RedirectResponse
    {
        $import = new UnitImport;
        Excel::import($import, $request->file('file'));

        $count = $import->getImportedCount();

        AuditService::log('imported', null, [], ['count' => $count], "Imported {$count} quantity entries from file.");

        return redirect()->route('units.index')
            ->with('success', "Successfully imported {$count} quantity entr".($count === 1 ? 'y' : 'ies').'.');
    }

    /**
     * Download example CSV template for quantity entry import.
     */
    public function downloadTemplate(): Response
    {
        $this->authorize('viewAny', Unit::class);

        $headers = [
            'Product Name',
            'Quantity',
            'Unit',
            'Note',
        ];

        $sampleRows = [
            ['Screen Protector', '20', 'Box', '10 pcs per box'],
            ['Phone Charger 20W', '50', 'Pcs', 'Fast chargers'],
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($sampleRows as $sampleRow) {
            fputcsv($handle, $sampleRow);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="unit_import_template.csv"',
        ]);
    }

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        [$columns, $units] = $this->exportData($request);

        return view('units.pdf', [
            'units' => $units,
            'columns' => $columns,
        ]);
    }

    /**
     * Show clean print layout matching the reference layout.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        [$columns, $units] = $this->exportData($request);

        return view('units.print', [
            'units' => $units,
            'columns' => $columns,
        ]);
    }

    /**
     * Export quantity entries to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', Unit::class);

        $search = $request->string('search')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'units_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new UnitExport($search ?: null, $columns ?: null),
            $filename
        );
    }

    /**
     * Resolve the visible columns and matching quantity entries for PDF / print views.
     *
     * @return array{0: array<string, string>, 1: Collection<int, Unit>}
     */
    private function exportData(Request $request): array
    {
        $search = $request->string('search')->toString();
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'product_name' => 'Product Name',
            'quantity' => 'Quantity',
            'unit' => 'Unit',
            'note' => 'Note',
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

        $units = Unit::query()
            ->when($search !== '', fn ($q) => $q->search($search))
            ->orderBy('product_name')
            ->get();

        return [$columns, $units];
    }
}
