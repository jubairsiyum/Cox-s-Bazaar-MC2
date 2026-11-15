<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'type' => StockMovementType::Adjustment,
            'quantity' => fake()->numberBetween(-50, 50),
            'quantity_after' => fake()->numberBetween(0, 500),
            'note' => fake()->sentence(),
        ];
    }
}
