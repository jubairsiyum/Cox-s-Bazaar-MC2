<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReturnCondition;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturnItem>
 */
class SalesReturnItemFactory extends Factory
{
    protected $model = SalesReturnItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = (float) fake()->numberBetween(50, 500);

        return [
            'sales_return_id' => SalesReturn::factory(),
            'unit_id' => Unit::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
            'condition' => ReturnCondition::Good,
            'restock' => true,
            'reason' => null,
        ];
    }
}
