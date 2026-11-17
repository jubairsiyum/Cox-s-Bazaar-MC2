<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnItem>
 */
class PurchaseReturnItemFactory extends Factory
{
    protected $model = PurchaseReturnItem::class;

    public function definition(): array
    {
        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'unit_id' => Unit::factory(),
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
        ];
    }
}
