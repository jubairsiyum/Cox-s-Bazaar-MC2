<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerCollection;
use App\Models\User;
use App\Services\SalesScope;

class CustomerCollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customer-collections.manage');
    }

    public function view(User $user, CustomerCollection $customerCollection): bool
    {
        return $this->viewAny($user) && SalesScope::canAccessRecord($user, $customerCollection);
    }

    public function create(User $user): bool
    {
        return $user->can('customer-collections.manage');
    }

    public function update(User $user, CustomerCollection $customerCollection): bool
    {
        return $user->can('customer-collections.manage')
            && SalesScope::canAccessRecord($user, $customerCollection);
    }

    public function delete(User $user, CustomerCollection $customerCollection): bool
    {
        return $this->update($user, $customerCollection);
    }
}
