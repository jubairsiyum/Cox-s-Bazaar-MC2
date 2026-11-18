<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderItem>
 */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = (float) fake()->numberBetween(50, 500);

        return [
            'sales_order_id' => SalesOrder::factory(),
            'unit_id' => Unit::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
