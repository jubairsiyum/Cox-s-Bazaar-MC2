<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock-transfers.manage');
    }

    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('stock-transfers.manage');
    }

    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage');
    }

    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('stock-transfers.manage');
    }
}
