<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\SalesScope;

class SalesInvoicePolicy
{
    /**
     * @var array<string>
     */
    protected const VIEW_PERMISSIONS = ['sales-invoices.view', 'sales-invoices.manage', 'sales-invoices.post'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(self::VIEW_PERMISSIONS);
    }

    public function view(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->viewAny($user) && SalesScope::canAccessRecord($user, $salesInvoice);
    }

    public function create(User $user): bool
    {
        return $user->can('sales-invoices.manage');
    }

    public function update(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can('sales-invoices.manage')
            && SalesScope::canAccessRecord($user, $salesInvoice)
            && $salesInvoice->status->isEditable();
    }

    public function delete(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->update($user, $salesInvoice);
    }

    /**
     * Post or void an invoice: stock-affecting actions.
     */
    public function post(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can('sales-invoices.post')
            && SalesScope::canAccessRecord($user, $salesInvoice);
    }

    /**
     * Void a posted invoice: same audience as posting.
     */
    public function void(User $user, SalesInvoice $salesInvoice): bool
    {
        return $this->post($user, $salesInvoice);
    }
}
