<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\LocationScope;
use App\Services\SupplierScope;

/**
 * Purchase orders are created through the Purchase Orders section
 * (purchase-orders.manage) and managed from the Purchase Invoices register
 * (purchase-invoices.* permissions). Outlet Managers are limited to their
 * assigned location. Purchases take effect on creation, so approve/reject
 * only remain for legacy documents still awaiting sign-off. Supplier logins
 * are read-only and see only their own supplier's purchases.
 */
class PurchaseOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSupplierPortalUser() && ! in_array($ability, ['viewAny', 'view'], true)) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['purchase-invoices.view', 'purchase-invoices.manage', 'purchase-invoices.post']);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->viewAny($user)
            && LocationScope::canAccess($user, $purchaseOrder->location_id)
            && SupplierScope::canAccess($user, $purchaseOrder->supplier_id);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.manage');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-invoices.manage')
            && LocationScope::canAccess($user, $purchaseOrder->location_id);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->update($user, $purchaseOrder);
    }

    /**
     * Complete a legacy purchase awaiting sign-off: the responsible manager of
     * its location.
     */
    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-invoices.approve')
            && LocationScope::canAccess($user, $purchaseOrder->location_id);
    }

    /**
     * Reject a purchase: same audience as approval.
     */
    public function reject(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->approve($user, $purchaseOrder);
    }
}
