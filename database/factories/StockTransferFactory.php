<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockTransferStatus;
use App\Models\Location;
use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'transfer_date' => now(),
            'status' => StockTransferStatus::Draft->value,
            'from_location_id' => Location::factory(),
            'to_location_id' => Location::factory(),
            'shipping_charges' => 0,
            'total_amount' => 0,
            'additional_notes' => fake()->sentence(),
        ];
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StockTransferStatus::InTransit->value,
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StockTransferStatus::Received->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StockTransferStatus::Cancelled->value,
        ]);
    }
}
