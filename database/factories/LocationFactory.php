<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->lexify('??##')),
            'type' => fake()->randomElement(array_column(LocationType::cases(), 'value')),
            'status' => 'active',
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'note' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }

    public function warehouse(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LocationType::Warehouse->value,
        ]);
    }

    public function brandShop(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LocationType::BrandShop->value,
        ]);
    }
}
