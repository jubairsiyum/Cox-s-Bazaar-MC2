<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Business locations used as stock transfer sources and destinations.
     *
     * @var array<string, array{code: string, type: LocationType, note: string}>
     */
    protected const LOCATIONS = [
        'Main Warehouse' => [
            'code' => 'WH01',
            'type' => LocationType::Warehouse,
            'note' => 'Central warehouse and distribution hub',
        ],
        'Brand Shop - Cox\'s Bazar' => [
            'code' => 'BS01',
            'type' => LocationType::BrandShop,
            'note' => 'Company-operated brand shop outlet',
        ],
        'SR Placement Store' => [
            'code' => 'SR01',
            'type' => LocationType::SrPlacement,
            'note' => 'Stock placed with sales representatives',
        ],
    ];

    /**
     * Seed the locations table.
     */
    public function run(): void
    {
        foreach (self::LOCATIONS as $name => $attributes) {
            Location::firstOrCreate(
                ['name' => $name],
                [
                    'code' => $attributes['code'],
                    'type' => $attributes['type'],
                    'status' => 'active',
                    'note' => $attributes['note'],
                ]
            );
        }
    }
}
