<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery leg of an approved product request: the product is on its way to
 * the requesting branch ("in transit") until that branch receives it
 * ("delivered"). Only applies once the request itself is approved.
 */
enum ProductRequestDeliveryStatus: string
{
    case InTransit = 'in_transit';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::InTransit => 'In Transit',
            self::Delivered => 'Delivered',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InTransit => 'in_transit',
            self::Delivered => 'delivered',
        };
    }
}
