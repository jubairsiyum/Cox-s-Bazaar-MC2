<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 500),
            'unit' => fake()->randomElement(Unit::UNITS),
            'note' => fake()->sentence(),
        ];
    }

    public function bulk(): static
    {
        return $this->state([
            'unit' => fake()->randomElement(['Box', 'Pack', 'Carton']),
            'quantity' => fake()->numberBetween(10, 100),
        ]);
    }
}
