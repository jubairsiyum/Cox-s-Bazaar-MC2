<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\BrandExport;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    /**
     * Columns that the list can be sorted by.
     *
     * @var array<string>
     */
    protected const SORTABLE = ['name', 'note'];

    /**
     * Display a listing of brands.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE, true)
            ? $request->string('sort')->toString()
            : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $brands = Brand::query()
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('brands.index', [
            'brands' => $brands,
            'perPage' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Store a newly created brand in storage.
     */
    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $brand = Brand::create($validated);

        AuditService::created($brand, "Brand '{$brand->name}' created.");

        return redirect()->route('brands.index')
            ->with('success', "Brand '{$brand->name}' created successfully.");
    }

    /**
     * Update the specified brand in storage.
     */
    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $original = $brand->getOriginal();
        $validated = $request->validated();

        $brand->update($validated);

        AuditService::updated($brand, $original, "Brand '{$brand->name}' updated.");

        return redirect()->route('brands.index')
            ->with('success', "Brand '{$brand->name}' updated successfully.");
    }

    /**
     * Remove the specified brand from storage.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        AuditService::deleted($brand, "Brand '{$brand->name}' deleted.");

        return redirect()->route('brands.index')
            ->with('success', "Brand '{$brand->name}' deleted successfully.");
    }

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        [$columns, $brands] = $this->exportData($request);

        return view('brands.pdf', [
            'brands' => $brands,
            'columns' => $columns,
        ]);
    }

    /**
     * Show clean print layout matching the reference layout.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', Brand::class);

        [$columns, $brands] = $this->exportData($request);

        return view('brands.print', [
            'brands' => $brands,
            'columns' => $columns,
        ]);
    }

    /**
     * Export brands to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', Brand::class);

        $search = $request->string('search')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'brands_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new BrandExport($search ?: null, $columns ?: null),
            $filename
        );
    }

    /**
     * Resolve the visible columns and matching brands for PDF / print views.
     *
     * @return array{0: array<string, string>, 1: Collection<int, Brand>}
     */
    private function exportData(Request $request): array
    {
        $search = $request->string('search')->toString();
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'name' => 'Brands',
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

        $brands = Brand::query()
            ->when($search !== '', fn ($q) => $q->search($search))
            ->orderBy('name')
            ->get();

        return [$columns, $brands];
    }
}
