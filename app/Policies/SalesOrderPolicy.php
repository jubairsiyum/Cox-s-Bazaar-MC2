<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SalesScope;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales-orders.manage');
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $this->viewAny($user) && SalesScope::canAccessRecord($user, $salesOrder);
    }

    public function create(User $user): bool
    {
        return $user->can('sales-orders.manage');
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->can('sales-orders.manage')
            && SalesScope::canAccessRecord($user, $salesOrder);
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $this->update($user, $salesOrder);
    }
}
