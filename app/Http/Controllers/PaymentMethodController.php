<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Services\AuditService;
use App\Services\DuplicateCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reference-data management for Finance: the payment methods that populate the
 * "Method" dropdown of transactions, account transfers and supplier payments.
 */
class PaymentMethodController extends Controller
{
    /**
     * List payment methods with search and status filtering.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $methods = PaymentMethod::query()
            ->with('creator')
            ->search($request->string('search')->toString())
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('payment-methods.index', [
            'methods' => $methods,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Show the form for creating a new payment method.
     */
    public function create(): View
    {
        $this->authorize('create', PaymentMethod::class);

        return view('payment-methods.create', [
            'paymentMethod' => new PaymentMethod,
        ]);
    }

    /**
     * Store a new payment method. Re-using the name of an archived method
     * restores that record instead of failing on the unique name.
     */
    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? 'active';
        $validated['created_by'] = $request->user()?->id;

        $reusable = DuplicateCheckService::reusableRecord(PaymentMethod::class, $validated);

        if ($reusable instanceof PaymentMethod) {
            DuplicateCheckService::purgeTrashConflicts(PaymentMethod::class, $validated, $reusable->id);

            $reusable->fill($validated);
            $reusable->restore();

            AuditService::created($reusable, "Payment method '{$reusable->name}' restored and updated.");

            return redirect()->route('payment-methods.index')
                ->with('success', "Payment method '{$reusable->name}' restored and updated successfully.");
        }

        $method = PaymentMethod::create($validated);

        AuditService::created($method, "Payment method '{$method->name}' created.");

        return redirect()->route('payment-methods.index')
            ->with('success', "Payment method '{$method->name}' created successfully.");
    }

    /**
     * Show the form for editing a payment method.
     */
    public function edit(PaymentMethod $paymentMethod): View
    {
        $this->authorize('update', $paymentMethod);

        return view('payment-methods.edit', [
            'paymentMethod' => $paymentMethod,
        ]);
    }

    /**
     * Update a payment method in storage.
     */
    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $original = $paymentMethod->getOriginal();
        $validated = $request->validated();

        DuplicateCheckService::purgeTrashConflicts(PaymentMethod::class, $validated, $paymentMethod->id);

        $paymentMethod->update($validated);

        AuditService::updated($paymentMethod, $original, "Payment method '{$paymentMethod->name}' updated.");

        return redirect()->route('payment-methods.index')
            ->with('success', "Payment method '{$paymentMethod->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status. Inactive methods drop out of the Finance
     * dropdowns while old entries keep their stored value.
     */
    public function toggleStatus(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('toggleStatus', $paymentMethod);

        $oldStatus = $paymentMethod->status;
        $newStatus = $paymentMethod->status === 'active' ? 'inactive' : 'active';

        $paymentMethod->update(['status' => $newStatus]);

        AuditService::log(
            'updated',
            $paymentMethod,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            "Payment method '{$paymentMethod->name}' status changed to {$newStatus}."
        );

        return back()->with('success', "Payment method '{$paymentMethod->name}' status updated to ".ucfirst($newStatus).'.');
    }

    /**
     * Delete a payment method. Deletion is blocked while entries still
     * reference the name — deactivate the method instead so history stays
     * intact.
     */
    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('delete', $paymentMethod);

        $usages = $paymentMethod->usageCount();

        if ($usages > 0) {
            return back()->with('error', sprintf(
                "Payment method '%s' is used by %d %s — mark it inactive instead of deleting it.",
                $paymentMethod->name,
                $usages,
                Str::plural('entry', $usages)
            ));
        }

        $paymentMethod->delete();

        AuditService::deleted($paymentMethod, "Payment method '{$paymentMethod->name}' deleted.");

        return redirect()->route('payment-methods.index')
            ->with('success', "Payment method '{$paymentMethod->name}' deleted successfully.");
    }
}
