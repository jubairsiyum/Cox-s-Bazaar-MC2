<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferItem>
 */
class StockTransferItemFactory extends Factory
{
    protected $model = StockTransferItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 50, 500);

        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'unit_id' => Unit::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
