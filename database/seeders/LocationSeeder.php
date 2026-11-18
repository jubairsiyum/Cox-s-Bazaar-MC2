<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LocationType;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Business locations used as stock transfer sources and destinations.
     *
     * @var array<string, array{code: string, type: LocationType, branch: string, note: string}>
     */
    protected const LOCATIONS = [
        'Main Warehouse' => [
            'code' => 'WH01',
            'type' => LocationType::Warehouse,
            'branch' => 'Head Office - Cox\'s Bazar',
            'note' => 'Central warehouse and distribution hub',
        ],
        'Brand Shop - Cox\'s Bazar' => [
            'code' => 'BS01',
            'type' => LocationType::BrandShop,
            'branch' => 'Sadar Outlet',
            'note' => 'Company-operated brand shop outlet',
        ],
        'SR Placement Store' => [
            'code' => 'SR01',
            'type' => LocationType::SrPlacement,
            'branch' => 'Head Office - Cox\'s Bazar',
            'note' => 'Stock placed with sales representatives',
        ],
    ];

    /**
     * Seed the locations table.
     */
    public function run(): void
    {
        foreach (self::LOCATIONS as $name => $attributes) {
            $location = Location::withTrashed()->firstOrCreate(
                ['name' => $name],
                [
                    'code' => $attributes['code'],
                    'type' => $attributes['type'],
                    'branch_id' => Branch::where('name', $attributes['branch'])->value('id'),
                    'status' => 'active',
                    'note' => $attributes['note'],
                ]
            );

            if ($location->trashed()) {
                $location->restore();
            }

            if (! $location->branch_id) {
                $location->update(['branch_id' => Branch::where('name', $attributes['branch'])->value('id')]);
            }
        }
    }
}
