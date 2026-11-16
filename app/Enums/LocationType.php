<?php

declare(strict_types=1);

namespace App\Enums;

enum LocationType: string
{
    case Warehouse = 'warehouse';
    case BrandShop = 'brand_shop';
    case SrPlacement = 'sr_placement';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse => 'Warehouse',
            self::BrandShop => 'Brand Shop',
            self::SrPlacement => 'SR Placement',
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
