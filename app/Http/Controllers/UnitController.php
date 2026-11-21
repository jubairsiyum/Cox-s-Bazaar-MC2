<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\UnitExport;
use App\Http\Requests\Unit\ImportUnitRequest;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Imports\UnitImport;
use App\Models\Product;
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
            ->with('product')
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->tap(fn ($q) => $sort === 'product_name'
                ? $q->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'), $direction)
                : $q->orderBy($sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return view('units.index', [
            'units' => $units,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;

        // A quantity entry is a product variant: creating one creates the product too.
        $product = Product::create([
            'name' => $validated['product_name'],
            'storage' => $validated['storage'] ?? null,
            'color' => $validated['color'] ?? null,
            'status' => 'active',
            'created_by' => $userId,
        ]);

        $unit = $product->stockRow()->create([
            'quantity' => (int) $validated['quantity'],
            'unit' => $validated['unit'],
            'note' => $validated['note'] ?? null,
            'created_by' => $userId,
        ]);

        AuditService::created($unit, "Quantity '{$unit->quantity} {$unit->unit}' for '{$unit->displayName()}' created.");

        return redirect()->route('units.index')
            ->with('success', "Quantity for '{$unit->displayName()}' created successfully.");
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $original = $unit->getOriginal();
        $validated = $request->validated();

        $unit->product->update([
            'name' => $validated['product_name'],
            'storage' => $validated['storage'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        // Quantity changes go through the stock ledger; metadata is saved below.
        StockService::adjust($unit, (int) $validated['quantity']);
        unset($validated['quantity'], $validated['product_name'], $validated['storage'], $validated['color']);

        $unit->update($validated);
        $unit->refresh();

        AuditService::updated($unit, $original, "Quantity '{$unit->quantity} {$unit->unit}' for '{$unit->displayName()}' updated.");

        return redirect()->route('units.index')
            ->with('success', "Quantity for '{$unit->displayName()}' updated successfully.");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        // A product variant and its stock row live and die together.
        $unit->product?->delete();
        $unit->delete();

        AuditService::deleted($unit, "Quantity '{$unit->quantity} {$unit->unit}' for '{$unit->displayName()}' deleted.");

        return redirect()->route('units.index')
            ->with('success', "Quantity for '{$unit->displayName()}' deleted successfully.");
    }

    public function importView(): View
    {
        $this->authorize('import', Unit::class);

        return view('units.import');
    }

    public function import(ImportUnitRequest $request): RedirectResponse
    {
        $import = new UnitImport;
        Excel::import($import, $request->file('file'));

        $count = $import->getImportedCount();

        AuditService::log('imported', null, [], ['count' => $count], "Imported {$count} quantity entries from file.");

        return redirect()->route('units.index')
            ->with('success', "Successfully imported {$count} quantity entr".($count === 1 ? 'y' : 'ies').'.');
    }

    public function downloadTemplate(): Response
    {
        $this->authorize('viewAny', Unit::class);

        $headers = [
            'Product Name',
            'Storage',
            'Color',
            'Quantity',
            'Unit',
            'Note',
        ];

        $sampleRows = [
            ['Screen Protector', '', '', '20', 'Box', '10 pcs per box'],
            ['Phone Charger 20W', '', '', '50', 'Pcs', 'Fast chargers'],
            ['iPhone 13', '128GB', 'Black', '10', 'Pcs', 'Variant example'],
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
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="unit_import_template.csv"',
        ]);
    }

    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        [$columns, $units] = $this->exportData($request);

        return view('units.pdf', [
            'units' => $units,
            'columns' => $columns,
        ]);
    }

    public function printView(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        [$columns, $units] = $this->exportData($request);

        return view('units.print', [
            'units' => $units,
            'columns' => $columns,
        ]);
    }

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
            ->with('product')
            ->when($search !== '', fn ($q) => $q->search($search))
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'units.product_id'))
            ->get();

        return [$columns, $units];
    }
}
