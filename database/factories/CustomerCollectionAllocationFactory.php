<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomerCollection;
use App\Models\CustomerCollectionAllocation;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCollectionAllocation>
 */
class CustomerCollectionAllocationFactory extends Factory
{
    protected $model = CustomerCollectionAllocation::class;

    public function definition(): array
    {
        return [
            'customer_collection_id' => CustomerCollection::factory(),
            'sales_invoice_id' => SalesInvoice::factory(),
            'amount' => (float) fake()->numberBetween(100, 1000),
        ];
    }
}
