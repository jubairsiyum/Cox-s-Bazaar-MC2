<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockAdjustmentType;
use App\Models\Location;
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
            'location_id' => Location::factory(),
            'type' => StockAdjustmentType::Normal->value,
            'total_amount' => 0,
            'total_amount_recovered' => 0,
            'reason' => fake()->sentence(),
        ];
    }

    public function abnormal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => StockAdjustmentType::Abnormal->value,
        ]);
    }
}
