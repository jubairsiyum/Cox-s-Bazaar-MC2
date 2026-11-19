<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\SalesScope;

/**
 * Sales invoice authorization: the sales-invoices.manage permission plus the
 * usual branch/location scope.
 *
 * Business rule implemented here: only unposted (draft) invoices may be
 * edited or deleted — posting finalises a document (and issues its stock).
 * Note that the Super Admin Gate bypass in AppServiceProvider short-circuits
 * these policy answers, so the Blade edit/delete buttons additionally check
 * the document state themselves; the controllers keep a matching guard for
 * direct URL access.
 */
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

    /**
     * Update (or delete) an invoice: permission + scope, and only while the
     * document is still a draft — the same rule the edit/delete buttons use
     * for their visibility.
     */
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
