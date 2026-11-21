<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;

class SupplierPolicy
{
    /**
     * Supplier logins are limited to their own profile: they may view it, answer
     * its purchase orders (recordOrderStatus), send or withdraw payment requests
     * (requestPayment), edit their own details (updateOwnProfile) and edit their
     * own open payment requests (editPaymentRequest), all for their own profile
     * only. Every other ability stays denied to them.
     */
    public function before(User $user, string $ability): ?bool
    {
        $supplierAbilities = ['viewAny', 'view', 'recordOrderStatus', 'requestPayment', 'updateOwnProfile', 'editPaymentRequest'];

        if ($user->isSupplierPortalUser() && ! in_array($ability, $supplierAbilities, true)) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view') || $user->can('suppliers.manage');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        if ($user->isSupplierPortalUser()) {
            return $this->isOwnProfile($user, $supplier);
        }

        return $user->can('suppliers.view') || $user->can('suppliers.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }

    public function toggleStatus(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }

    public function import(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    /**
     * Record the supplier's acceptance of its purchase orders on the supplier
     * profile. A supplier login answers only for its own linked profile.
     */
    public function recordOrderStatus(User $user, Supplier $supplier): bool
    {
        if ($user->isSupplierPortalUser()) {
            return $this->isOwnProfile($user, $supplier);
        }

        return $user->can('suppliers.manage') || $user->can('purchase-invoices.manage');
    }

    /**
     * Send or withdraw payment requests: staff on the supplier's behalf, a
     * supplier login on its own profile.
     */
    public function requestPayment(User $user, Supplier $supplier): bool
    {
        if ($user->isSupplierPortalUser()) {
            return $this->isOwnProfile($user, $supplier);
        }

        return $user->can('suppliers.manage');
    }

    /**
     * Edit the supplier's own identity, contact and address details. Supplier
     * logins only, on their own profile; staff keep the Suppliers edit screen.
     */
    public function updateOwnProfile(User $user, Supplier $supplier): bool
    {
        return $user->isSupplierPortalUser() && $this->isOwnProfile($user, $supplier);
    }

    /**
     * Edit an open payment request the supplier sent on its own profile. A
     * request that is actioned or cancelled, or belongs to another supplier, is
     * not editable.
     */
    public function editPaymentRequest(User $user, Supplier $supplier, SupplierPaymentRequest $paymentRequest): bool
    {
        return $user->isSupplierPortalUser()
            && $this->isOwnProfile($user, $supplier)
            && (int) $paymentRequest->supplier_id === (int) $supplier->id
            && $paymentRequest->isOpen();
    }

    /**
     * A supplier login may open only the supplier profile linked to it.
     */
    private function isOwnProfile(User $user, Supplier $supplier): bool
    {
        return $supplier->user_id !== null && (int) $supplier->user_id === (int) $user->id;
    }
}
