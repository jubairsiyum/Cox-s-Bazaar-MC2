<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Purchase orders are created through the Purchase Orders section
 * (purchase-orders.manage) and managed from the Purchase Invoices register
 * (purchase-invoices.* permissions).
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['purchase-invoices.view', 'purchase-invoices.manage', 'purchase-invoices.post']);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasAnyPermission(['purchase-invoices.view', 'purchase-invoices.manage', 'purchase-invoices.post']);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase-orders.manage');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-invoices.manage');
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase-invoices.manage');
    }
}
