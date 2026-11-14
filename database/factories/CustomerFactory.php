<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile' => fake()->phoneNumber(),
            'tel_no' => fake()->optional()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'country' => 'Bangladesh',
            'state' => 'Chittagong',
            'city' => "Cox's Bazar",
            'postcode' => fake()->postcode(),
            'address' => fake()->streetAddress(),
            'note' => fake()->optional()->sentence(),
            'custom_fields' => null,
            'status' => 'active',
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
