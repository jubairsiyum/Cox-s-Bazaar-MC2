<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Sample quantity data for the mobile shop.
     *
     * @var array<int, array{product_name: string, quantity: int, unit: string, note: string|null}>
     */
    protected const ENTRIES = [
        ['product_name' => 'iPhone 15', 'quantity' => 25, 'unit' => 'Pcs', 'note' => '128GB / 256GB'],
        ['product_name' => 'Samsung Galaxy A55', 'quantity' => 30, 'unit' => 'Pcs', 'note' => null],
        ['product_name' => 'Xiaomi Redmi Note 13', 'quantity' => 40, 'unit' => 'Pcs', 'note' => null],
        ['product_name' => 'Phone Charger 20W', 'quantity' => 50, 'unit' => 'Pcs', 'note' => 'Fast chargers'],
        ['product_name' => 'USB Type-C Cable', 'quantity' => 20, 'unit' => 'Box', 'note' => '12 pcs per box'],
        ['product_name' => 'Tempered Glass Screen Protector', 'quantity' => 25, 'unit' => 'Box', 'note' => '10 pcs per box'],
        ['product_name' => 'Phone Back Cover', 'quantity' => 30, 'unit' => 'Pack', 'note' => 'Assorted models'],
        ['product_name' => 'Power Bank 10000mAh', 'quantity' => 12, 'unit' => 'Box', 'note' => '6 pcs per box'],
        ['product_name' => 'Bluetooth Earbuds', 'quantity' => 6, 'unit' => 'Box', 'note' => '10 pcs per box'],
        ['product_name' => 'Memory Card 64GB', 'quantity' => 100, 'unit' => 'Pcs', 'note' => null],
        ['product_name' => 'Smart Watch Band', 'quantity' => 12, 'unit' => 'Dozen', 'note' => null],
        ['product_name' => 'Feature Phone Nokia 105', 'quantity' => 5, 'unit' => 'Box', 'note' => '20 pcs per box'],
    ];

    /**
     * Seed the quantity entries together with their product variants.
     */
    public function run(): void
    {
        foreach (self::ENTRIES as $entry) {
            $product = Product::firstOrCreate(
                ['name' => $entry['product_name'], 'storage' => null, 'color' => null],
                ['status' => 'active']
            );

            $unit = Unit::withTrashed()->firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => $entry['quantity'], 'unit' => $entry['unit'], 'note' => $entry['note']]
            );

            if ($unit->trashed()) {
                $unit->restore();
            }
        }
    }
}
