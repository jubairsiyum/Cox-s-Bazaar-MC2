<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Business branches used for branch-wise user assignment and reporting.
     *
     * @var array<string, array{code: string, address: string, phone: string}>
     */
    protected const BRANCHES = [
        'Head Office - Cox\'s Bazar' => [
            'code' => 'HO01',
            'address' => 'Kolatoli Road, Cox\'s Bazar',
            'phone' => '+8801700000000',
        ],
        'Sadar Outlet' => [
            'code' => 'BR01',
            'address' => 'Sadar Market, Main Road, Cox\'s Bazar',
            'phone' => '+8801700000001',
        ],
        'Chakaria Outlet' => [
            'code' => 'BR02',
            'address' => 'Chakaria Bazar, Chakaria',
            'phone' => '+8801700000002',
        ],
        'Ukhia Outlet' => [
            'code' => 'BR03',
            'address' => 'Teknaf Road, Ukhia',
            'phone' => '+8801700000003',
        ],
    ];

    /**
     * Seed the branches table.
     */
    public function run(): void
    {
        foreach (self::BRANCHES as $name => $attributes) {
            Branch::firstOrCreate(
                ['name' => $name],
                [
                    'code' => $attributes['code'],
                    'address' => $attributes['address'],
                    'phone' => $attributes['phone'],
                    'status' => 'active',
                ]
            );
        }
    }
}
