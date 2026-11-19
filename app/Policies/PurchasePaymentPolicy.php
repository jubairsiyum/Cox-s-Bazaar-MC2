<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Models\PurchasePayment;
use App\Models\User;
use App\Services\SalesScope;

class PurchasePaymentPolicy
{
    /**
     * @var array<int, string>
     */
    protected const PERMISSIONS = [
        'purchase-invoices.view',
        'purchase-invoices.manage',
        'supplier-payments.view',
        'supplier-payments.manage',
    ];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(self::PERMISSIONS);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Pending requests may be edited or cancelled by their creator only.
     */
    public function update(User $user, PurchasePayment $purchasePayment): bool
    {
        return (int) $purchasePayment->created_by === (int) $user->id
            && $purchasePayment->status === ApprovalStatus::Pending;
    }

    public function delete(User $user, PurchasePayment $purchasePayment): bool
    {
        return $this->update($user, $purchasePayment);
    }

    /**
     * Approve a due payment request: the responsible branch manager of the
     * purchase's branch/location (or Super Admin). No dedicated permission is
     * required — the gate is the branch-manager relationship itself, mirroring
     * {@see SalesReturnPolicy::approve}. A user may never sign off their own
     * request: genuine segregation of duties (requests made by the responsible
     * manager themselves complete on creation and never reach this point).
     */
    public function approve(User $user, PurchasePayment $purchasePayment): bool
    {
        $order = $purchasePayment->purchaseOrder;

        return $order !== null
            && SalesScope::canApprove($user, $order)
            && (int) $purchasePayment->created_by !== (int) $user->id;
    }

    /**
     * Reject a due payment request: same audience as approval, and the same
     * rule that the requester may not decide their own request.
     */
    public function reject(User $user, PurchasePayment $purchasePayment): bool
    {
        return $this->approve($user, $purchasePayment);
    }
}
