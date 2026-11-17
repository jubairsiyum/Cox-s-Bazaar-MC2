<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockAdjustmentType;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'adjustment_date' => now(),
            'type' => StockAdjustmentType::Set->value,
            'total_amount' => 0,
            'reason' => fake()->sentence(),
        ];
    }

    public function add(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => StockAdjustmentType::Add->value,
        ]);
    }

    public function remove(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => StockAdjustmentType::Remove->value,
        ]);
    }
}
