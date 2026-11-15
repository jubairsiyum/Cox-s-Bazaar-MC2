<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'supplier_id' => 'SP'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => fake()->company(),
            'mobile' => fake()->numerify('017########'),
            'email' => fake()->unique()->safeEmail(),
            'tel_no' => fake()->phoneNumber(),
            'tax_number' => 'TAX'.fake()->numerify('#####'),
            'opening_balance' => fake()->randomFloat(2, 0, 50000),
            'purchase_due' => fake()->randomFloat(2, 0, 100000),
            'purchase_return_due' => 0.00,
            'country' => 'Bangladesh',
            'state' => 'Chittagong',
            'city' => "Cox's Bazar",
            'postcode' => '4700',
            'address' => fake()->address(),
            'status' => 'active',
        ];
    }
}
