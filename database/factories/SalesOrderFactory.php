<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'order_date' => now(),
            'expected_date' => now()->addDays(3)->toDateString(),
            'customer_id' => Customer::factory(),
            'location_id' => Location::factory(),
            'branch_id' => null,
            'status' => SalesOrderStatus::Draft,
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'grand_total' => 0,
            'note' => null,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesOrderStatus::Confirmed,
        ]);
    }

    public function invoiced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SalesOrderStatus::Invoiced,
        ]);
    }
}
