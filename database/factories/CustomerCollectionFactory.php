<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCollection>
 */
class CustomerCollectionFactory extends Factory
{
    protected $model = CustomerCollection::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'collection_date' => now(),
            'amount' => 0,
            'method' => PaymentMethod::Cash,
            'reference_no' => null,
            'note' => null,
            'location_id' => Location::factory(),
            'branch_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
