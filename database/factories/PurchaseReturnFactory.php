<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => null,
            'return_date' => now(),
            'supplier_id' => Supplier::factory(),
            'location_id' => Location::factory(),
            'reason' => fake()->randomElement([
                'Defective or Damaged',
                'Wrong Product Delivered',
                'Quality Issues',
                'Overstock',
                'Expired Stock',
                'Other',
            ]),
            'subtotal' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'grand_total' => 0,
            'paid_amount' => 0,
            'document' => null,
        ];
    }

    public function forPurchase(PurchaseOrder $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'purchase_order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'location_id' => $order->location_id,
        ]);
    }
}
