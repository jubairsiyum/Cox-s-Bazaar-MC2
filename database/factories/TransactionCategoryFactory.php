<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionCategoryType;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionCategory>
 */
class TransactionCategoryFactory extends Factory
{
    protected $model = TransactionCategory::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => fake()->randomElement(TransactionCategoryType::cases())->value,
            'status' => 'active',
            'note' => null,
            'created_by' => User::factory(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionCategoryType::Income->value,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionCategoryType::Expense->value,
        ]);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionCategoryType::Both->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }
}
