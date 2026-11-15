<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\CategoryExport;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $categories = Category::query()
            ->when($request->filled('search'), fn ($q) => $q->search($request->string('search')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('categories.index', [
            'categories' => $categories,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('categories.create', [
            'category' => new Category,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $category = Category::create($validated);

        AuditService::created($category, "Category '{$category->name}' ({$category->category_id}) created.");

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('categories.edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $original = $category->getOriginal();
        $validated = $request->validated();

        $category->update($validated);

        AuditService::updated($category, $original, "Category '{$category->name}' ({$category->category_id}) updated.");

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        AuditService::deleted($category, "Category '{$category->name}' ({$category->category_id}) deleted.");

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' deleted successfully.");
    }

    /**
     * Toggle active/inactive status of the specified category.
     */
    public function toggleStatus(Category $category): RedirectResponse
    {
        $this->authorize('toggleStatus', $category);

        $oldStatus = $category->status;
        $newStatus = $category->status === 'active' ? 'inactive' : 'active';

        $category->update(['status' => $newStatus]);

        AuditService::log(
            'updated',
            $category,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Category '{$category->name}' ({$category->category_id}) status changed to {$newStatus}."
        );

        return back()->with('success', "Category '{$category->name}' status updated to ".ucfirst($newStatus).'.');
    }

    /**
     * Show clean PDF preview matching the reference layout.
     */
    public function pdfView(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        [$columns, $categories] = $this->exportData($request);

        return view('categories.pdf', [
            'categories' => $categories,
            'columns' => $columns,
        ]);
    }

    /**
     * Show clean print layout matching the reference layout.
     */
    public function printView(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        [$columns, $categories] = $this->exportData($request);

        return view('categories.print', [
            'categories' => $categories,
            'columns' => $columns,
        ]);
    }

    /**
     * Export categories to Excel or CSV.
     */
    public function export(Request $request, string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('viewAny', Category::class);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $columns = $request->string('columns')->toString();

        $filename = 'categories_'.now()->format('Y-m-d_His').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(
            new CategoryExport($search ?: null, $status ?: null, $columns ?: null),
            $filename
        );
    }

    /**
     * Resolve the visible columns and matching categories for PDF / print views.
     *
     * @return array{0: array<string, string>, 1: Collection<int, Category>}
     */
    private function exportData(Request $request): array
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $columnsParam = $request->string('columns')->toString();

        $availableColumns = [
            'name' => 'Category Name',
            'description' => 'Description',
            'status' => 'Status',
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

        $categories = Category::query()
            ->when($search !== '', fn ($q) => $q->search($search))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get();

        return [$columns, $categories];
    }
}
