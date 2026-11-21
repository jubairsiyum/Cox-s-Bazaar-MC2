<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TransactionCategory\StoreTransactionCategoryRequest;
use App\Http\Requests\TransactionCategory\UpdateTransactionCategoryRequest;
use App\Models\TransactionCategory;
use App\Services\AuditService;
use App\Services\DuplicateCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reference-data management for the Transactions area: the categories that
 * populate the "Category" dropdown of income/expense entries.
 */
class TransactionCategoryController extends Controller
{
    /**
     * List transaction categories with search and status filtering.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', TransactionCategory::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $categories = TransactionCategory::query()
            ->with('creator')
            ->search($request->string('search')->toString())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('transaction-categories.index', [
            'categories' => $categories,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $this->authorize('create', TransactionCategory::class);

        return view('transaction-categories.create', [
            'transactionCategory' => new TransactionCategory,
        ]);
    }

    /**
     * Store a new category. Re-using the name of an archived category restores
     * that record instead of failing on the unique name.
     */
    public function store(StoreTransactionCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['created_by'] = $request->user()?->id;

        $reusable = DuplicateCheckService::reusableRecord(TransactionCategory::class, $validated);

        if ($reusable instanceof TransactionCategory) {
            DuplicateCheckService::purgeTrashConflicts(TransactionCategory::class, $validated, $reusable->id);

            $reusable->fill($validated);
            $reusable->restore();

            AuditService::created($reusable, "Transaction category '{$reusable->name}' restored and updated.");

            return redirect()->route('transaction-categories.index')
                ->with('success', "Transaction category '{$reusable->name}' restored and updated successfully.");
        }

        $category = TransactionCategory::create($validated);

        AuditService::created($category, "Transaction category '{$category->name}' created.");

        return redirect()->route('transaction-categories.index')
            ->with('success', "Transaction category '{$category->name}' created successfully.");
    }

    /**
     * Show the form for editing a category.
     */
    public function edit(TransactionCategory $transactionCategory): View
    {
        $this->authorize('update', $transactionCategory);

        return view('transaction-categories.edit', [
            'transactionCategory' => $transactionCategory,
        ]);
    }

    /**
     * Update a category in storage.
     */
    public function update(UpdateTransactionCategoryRequest $request, TransactionCategory $transactionCategory): RedirectResponse
    {
        $original = $transactionCategory->getOriginal();
        $validated = $request->validated();

        DuplicateCheckService::purgeTrashConflicts(TransactionCategory::class, $validated, $transactionCategory->id);

        $transactionCategory->update($validated);

        AuditService::updated($transactionCategory, $original, "Transaction category '{$transactionCategory->name}' updated.");

        return redirect()->route('transaction-categories.index')
            ->with('success', "Transaction category '{$transactionCategory->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status. Inactive categories drop out of the
     * Finance dropdowns while old entries keep their stored value.
     */
    public function toggleStatus(TransactionCategory $transactionCategory): RedirectResponse
    {
        $this->authorize('toggleStatus', $transactionCategory);

        $oldStatus = $transactionCategory->status;
        $newStatus = $transactionCategory->status === 'active' ? 'inactive' : 'active';

        $transactionCategory->update(['status' => $newStatus]);

        AuditService::log(
            'updated',
            $transactionCategory,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Transaction category '{$transactionCategory->name}' status changed to {$newStatus}."
        );

        return back()->with('success', "Transaction category '{$transactionCategory->name}' status updated to ".ucfirst($newStatus).'.');
    }

    /**
     * Delete a category. Deletion is blocked while entries still reference the
     * name — deactivate the category instead so history stays intact.
     */
    public function destroy(TransactionCategory $transactionCategory): RedirectResponse
    {
        $this->authorize('delete', $transactionCategory);

        $usages = $transactionCategory->usageCount();

        if ($usages > 0) {
            return back()->with('error', sprintf(
                "Transaction category '%s' is used by %d %s — mark it inactive instead of deleting it.",
                $transactionCategory->name,
                $usages,
                Str::plural('entry', $usages)
            ));
        }

        $transactionCategory->delete();

        AuditService::deleted($transactionCategory, "Transaction category '{$transactionCategory->name}' deleted.");

        return redirect()->route('transaction-categories.index')
            ->with('success', "Transaction category '{$transactionCategory->name}' deleted successfully.");
    }
}
