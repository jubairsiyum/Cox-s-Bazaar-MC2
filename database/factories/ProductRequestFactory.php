<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductRequestStatus;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRequest>
 */
class ProductRequestFactory extends Factory
{
    protected $model = ProductRequest::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'status' => ProductRequestStatus::Pending->value,
            'name' => fake()->unique()->words(3, true),
            'model' => strtoupper(fake()->bothify('SM-####')),
            'barcode' => fake()->unique()->numerify('############'),
            'storage' => fake()->optional()->randomElement(['64GB', '128GB', '256GB']),
            'color' => fake()->optional()->randomElement(['Black', 'White', 'Blue']),
            'purchase_price' => fake()->randomFloat(2, 5000, 100000),
            'sale_price' => fake()->randomFloat(2, 6000, 120000),
            'quantity' => 0,
            'unit' => 'Pcs',
            'product_status' => 'active',
            'note' => fake()->optional()->sentence(),
            'requested_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductRequestStatus::Approved->value,
            'reviewed_at' => now(),
            'created_product_id' => Product::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductRequestStatus::Rejected->value,
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
