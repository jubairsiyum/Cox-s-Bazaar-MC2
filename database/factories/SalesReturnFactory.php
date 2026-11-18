<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalesReturnStatus;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturn>
 */
class SalesReturnFactory extends Factory
{
    protected $model = SalesReturn::class;

    public function definition(): array
    {
        return [
            'sales_invoice_id' => SalesInvoice::factory(),
            'customer_id' => null,
            'return_date' => now(),
            'location_id' => Location::factory(),
            'branch_id' => null,
            'status' => SalesReturnStatus::Pending,
            'reason' => fake()->randomElement([
                'Defective or Damaged',
                'Wrong Product Delivered',
                'Quality Issues',
                'Customer Changed Mind',
                'Other',
            ]),
            'refund_amount' => 0,
            'approved_at' => null,
            'restocked_at' => null,
            'approved_by' => null,
            'created_by' => User::factory(),
        ];
    }

    public function forInvoice(SalesInvoice $invoice): static
    {
        return $this->state(fn (array $attributes): array => [
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'location_id' => $invoice->location_id,
            'branch_id' => $invoice->branch_id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesReturnStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesReturnStatus::Rejected,
            'approved_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesReturnStatus::Completed,
            'approved_at' => now(),
            'restocked_at' => now(),
        ]);
    }
}
