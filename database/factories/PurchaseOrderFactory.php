<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'order_date' => now(),
            'supplier_id' => Supplier::factory(),
            'location_id' => Location::factory(),
            'purchase_status' => PurchaseStatus::Ordered->value,
            'subtotal' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'grand_total' => 0,
            'paid_amount' => 0,
            'document' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'purchase_status' => PurchaseStatus::Pending->value,
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes): array => [
            'purchase_status' => PurchaseStatus::Received->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'purchase_status' => PurchaseStatus::Cancelled->value,
        ]);
    }
}
