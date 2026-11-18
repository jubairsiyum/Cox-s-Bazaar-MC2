<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesInvoice>
 */
class SalesInvoiceFactory extends Factory
{
    protected $model = SalesInvoice::class;

    public function definition(): array
    {
        return [
            'invoice_date' => now(),
            'customer_id' => Customer::factory(),
            'sales_order_id' => null,
            'location_id' => Location::factory(),
            'branch_id' => null,
            'status' => SalesInvoiceStatus::Draft,
            'payment_status' => 'due',
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'grand_total' => 0,
            'credit_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'note' => null,
            'void_reason' => null,
            'posted_at' => null,
            'voided_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesInvoiceStatus::Posted,
            'posted_at' => now(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesInvoiceStatus::Voided,
            'voided_at' => now(),
        ]);
    }
}
