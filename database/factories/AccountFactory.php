<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $type = fake()->randomElement([AccountType::Cash, AccountType::Bank]);

        return [
            'name' => fake()->unique()->words(3, true).' account',
            'type' => $type->value,
            'account_number' => $type === AccountType::Bank ? fake()->numerify('####-######-###') : null,
            'bank_name' => $type === AccountType::Bank ? fake()->company().' Bank' : null,
            'bank_branch' => $type === AccountType::Bank ? fake()->city() : null,
            'opening_balance' => 0,
            'current_balance' => 0,
            'status' => 'active',
            'location_id' => null,
            'note' => null,
            'created_by' => User::factory(),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountType::Cash->value,
            'account_number' => null,
            'bank_name' => null,
            'bank_branch' => null,
        ]);
    }

    public function bank(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountType::Bank->value,
            'account_number' => fake()->numerify('####-######-###'),
            'bank_name' => fake()->company().' Bank',
            'bank_branch' => fake()->city(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes): array => [
            'opening_balance' => $balance,
            'current_balance' => $balance,
        ]);
    }

    public function at(?Location $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => $location?->id,
        ]);
    }
}
