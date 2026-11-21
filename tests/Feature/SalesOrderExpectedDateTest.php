<?php

use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('saves a sales order without an expected date', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $customer = Customer::factory()->create(['mobile' => '01711000777']);

    post(route('sales-orders.store'), [
        'order_date' => now()->format('Y-m-d\TH:i'),
        'expected_date' => '',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'mobile_no' => '01711000777',
        'location_id' => Location::factory()->create()->id,
        'tax_rate' => 0,
        'note' => null,
        'items' => [
            ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 100, 'discount' => 0],
        ],
    ])->assertRedirect();

    expect(SalesOrder::count())->toBe(1)
        ->and(SalesOrder::first()->expected_date)->toBeNull();
});

it('shows the expected date on the invoice print only when the order has one', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->create();
    $withDate = SalesOrder::factory()->create([
        'location_id' => $location->id,
        'expected_date' => '2026-12-05',
    ]);
    $withoutDate = SalesOrder::factory()->create([
        'location_id' => $location->id,
        'expected_date' => null,
    ]);

    $invoiceWithDate = SalesInvoice::factory()->create(['sales_order_id' => $withDate->id, 'location_id' => $location->id]);
    $invoiceWithoutDate = SalesInvoice::factory()->create(['sales_order_id' => $withoutDate->id, 'location_id' => $location->id]);

    get(route('sales-invoices.print', $invoiceWithDate))
        ->assertOk()
        ->assertSee('Expected:')
        ->assertSee('05/12/2026');

    get(route('sales-invoices.print', $invoiceWithoutDate))
        ->assertOk()
        ->assertDontSee('Expected:');
});
