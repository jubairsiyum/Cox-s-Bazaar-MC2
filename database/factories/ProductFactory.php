<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'product_id' => 'PRD'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => fake()->unique()->words(3, true),
            'model' => strtoupper(fake()->bothify('SM-####')),
            'barcode' => fake()->unique()->numerify('############'),
            'storage' => fake()->optional()->randomElement(['64GB', '128GB', '256GB', '512GB', '1TB']),
            'color' => fake()->optional()->randomElement(['Black', 'White', 'Blue', 'Red', 'Gold', 'Silver']),
            'purchase_price' => fake()->randomFloat(2, 5000, 100000),
            'sale_price' => fake()->randomFloat(2, 6000, 120000),
            'status' => 'active',
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
