<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\StockAdjustmentType;
use App\Models\StockAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'adjustment_date' => now(),
            'type' => StockAdjustmentType::Set->value,
            'approval_status' => ApprovalStatus::Pending->value,
            'total_amount' => 0,
            'reason' => fake()->sentence(),
        ];
    }

    public function add(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => StockAdjustmentType::Add->value,
        ]);
    }

    public function remove(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => StockAdjustmentType::Remove->value,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'approval_status' => ApprovalStatus::Approved->value,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'approval_status' => ApprovalStatus::Rejected->value,
        ]);
    }
}
