<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupplierPaymentRequestStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPaymentRequest>
 */
class SupplierPaymentRequestFactory extends Factory
{
    protected $model = SupplierPaymentRequest::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'location_id' => Location::factory(),
            'purchase_order_id' => null,
            'message' => fake()->sentence(8),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'status' => SupplierPaymentRequestStatus::Open->value,
            'created_by' => User::factory(),
            'actioned_by' => null,
            'payment_id' => null,
        ];
    }

    public function forOrder(PurchaseOrder $order): static
    {
        return $this->state(fn (array $attributes): array => [
            'supplier_id' => $order->supplier_id,
            'location_id' => $order->location_id,
            'purchase_order_id' => $order->id,
        ]);
    }

    public function actioned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupplierPaymentRequestStatus::Actioned->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupplierPaymentRequestStatus::Cancelled->value,
        ]);
    }
}
