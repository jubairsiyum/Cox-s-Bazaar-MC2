<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Restock = 'restock';
    case PurchaseReturn = 'purchase_return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening Stock',
            self::Sale => 'Sale',
            self::SaleReturn => 'Sale Return',
            self::Restock => 'Restock',
            self::PurchaseReturn => 'Purchase Return',
            self::Adjustment => 'Adjustment',
        };
    }
}
