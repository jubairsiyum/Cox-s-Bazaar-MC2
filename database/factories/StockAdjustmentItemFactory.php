<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentItem>
 */
class StockAdjustmentItemFactory extends Factory
{
    protected $model = StockAdjustmentItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 20);
        $unitPrice = fake()->randomFloat(2, 50, 500);

        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'unit_id' => Unit::factory(),
            'quantity' => $quantity,
            'quantity_before' => fake()->numberBetween(1, 50),
            'quantity_after' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
