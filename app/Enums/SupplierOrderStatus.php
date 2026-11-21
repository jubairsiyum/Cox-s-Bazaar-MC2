<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Supplier-side state of a purchase order.
 *
 * Mirrors the manager-facing purchase status one step behind the scenes:
 * a placed order sits "pending" until the supplier accepts it ("accepted"),
 * and the end state is "delivered" — the supplier's name for the manager
 * side's "Received".
 */
enum SupplierOrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Delivered => 'Delivered',
        };
    }

    /**
     * All selectable values as value => label pairs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
