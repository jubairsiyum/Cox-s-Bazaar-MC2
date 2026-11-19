<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountEntryStatus;
use App\Enums\AccountMethod;
use App\Enums\AccountTransactionType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountTransaction>
 */
class AccountTransactionFactory extends Factory
{
    protected $model = AccountTransaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'type' => AccountTransactionType::Expense->value,
            'category' => fake()->randomElement(AccountTransaction::CATEGORIES),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'transaction_date' => now()->toDateString(),
            'method' => fake()->randomElement([AccountMethod::Cash->value, AccountMethod::Bank->value, AccountMethod::Other->value]),
            'reference_no' => null,
            'note' => null,
            'status' => AccountEntryStatus::Pending->value,
            'created_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountTransactionType::Income->value,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountTransactionType::Expense->value,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountEntryStatus::Pending->value,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountEntryStatus::Completed->value,
            'approved_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountEntryStatus::Cancelled->value,
            'approved_at' => now(),
        ]);
    }
}
