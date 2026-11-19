<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchasePayment>
 */
class PurchasePaymentFactory extends Factory
{
    protected $model = PurchasePayment::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'amount' => fake()->randomFloat(2, 1, 1000),
            'method' => PaymentMethod::Cash->value,
            'note' => null,
            'status' => ApprovalStatus::Pending->value,
            'created_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Pending->value,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Rejected->value,
            'approved_at' => now(),
        ]);
    }
}
