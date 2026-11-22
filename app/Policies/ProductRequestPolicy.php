<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductRequest;
use App\Models\User;
use App\Services\BranchScope;

class ProductRequestPolicy
{
    /**
     * The same audience that can see the products page.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('products.view') || $user->can('products.manage');
    }

    /**
     * The Super Admin sees every request. Everyone else sees their own branch's
     * requests and the ones they raised themselves.
     */
    public function view(User $user, ProductRequest $productRequest): bool
    {
        return $this->viewAny($user)
            && (
                BranchScope::isUnrestricted($user)
                || $productRequest->requested_by === $user->id
                || BranchScope::canAccessRecord($user, $productRequest)
            );
    }

    /**
     * Anyone who can see the products page and works at a branch may ask for a
     * new product. A request creates nothing by itself.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user)
            && (BranchScope::locationId($user) !== null || BranchScope::isUnrestricted($user));
    }

    /**
     * Only the Super Admin approves, and only a request still awaiting a decision.
     */
    public function approve(User $user, ProductRequest $productRequest): bool
    {
        return $user->isSuperAdmin() && $productRequest->isPending();
    }

    /**
     * Only the Super Admin rejects, under the same conditions as approval.
     */
    public function reject(User $user, ProductRequest $productRequest): bool
    {
        return $this->approve($user, $productRequest);
    }
}
