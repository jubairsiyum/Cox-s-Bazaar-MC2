<?php

declare(strict_types=1);

use App\Enums\SalesInvoiceStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A distributor invoice with one line item — order-generated when a
 * sales_order_id is passed, POS/direct otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function distributorInvoice(Unit $unit, array $attributes = []): SalesInvoice
{
    $invoice = SalesInvoice::factory()->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'location_id' => Location::factory()->create()->id,
    ], $attributes));

    $invoice->items()->create([
        'unit_id' => $unit->id,
        'quantity' => 2,
        'unit_price' => 500,
        'discount' => 0,
        'subtotal' => 1000,
    ]);

    $invoice->update(['subtotal' => 1000, 'grand_total' => 1000]);

    return $invoice->fresh();
}

it('lists every distributor invoice including pos invoices for super admins', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['quantity' => 50]);
    $fromOrder = distributorInvoice($unit, ['sales_order_id' => SalesOrder::factory()->create()->id]);
    $fromPos = distributorInvoice($unit);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertSee('Collections')
        ->assertSee($fromOrder->invoice_no)
        ->assertSee($fromPos->invoice_no);
});

it('scopes the collections list to the own location for outlet managers', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 50]);

    $myOrderInvoice = distributorInvoice($unit, ['location_id' => $mine->id, 'sales_order_id' => SalesOrder::factory()->create()->id]);
    $myPosInvoice = distributorInvoice($unit, ['location_id' => $mine->id]);
    $otherInvoice = distributorInvoice($unit, ['location_id' => $other->id]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo(['sales-invoices.view']);

    $this->actingAs($manager)
        ->get(route('collections.index'))
        ->assertOk()
        ->assertSee($myOrderInvoice->invoice_no)
        ->assertSee($myPosInvoice->invoice_no)
        ->assertDontSee($otherInvoice->invoice_no);
});

it('404s the retired customer collections screens', function () {
    actingAsSuperAdmin();

    $this->get('/customer-collections')->assertNotFound();

    $this->get('/customer-collections/create')->assertNotFound();

    $this->get('/customer-collections/customer-invoices')->assertNotFound();
});

it('shows the complete display details for order-generated distributor invoices', function () {
    actingAsSuperAdmin();

    $galaxy = stockRowFor('Samsung Galaxy A15');
    $vivo = stockRowFor('Vivo Y28');

    $customer = Customer::factory()->create(['name' => 'Bismillah Telecom', 'city' => 'Chakaria']);
    $location = Location::factory()->create(['name' => 'Ukhia Outlet']);

    $invoice = SalesInvoice::factory()->create([
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'sales_order_id' => SalesOrder::factory()->create()->id,
        'invoice_date' => Carbon::create(2026, 11, 15, 10, 45),
        'status' => SalesInvoiceStatus::Posted,
        'posted_at' => Carbon::create(2026, 11, 15, 10, 45),
    ]);

    $invoice->items()->create(['unit_id' => $galaxy->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 0, 'subtotal' => 1000]);
    $invoice->items()->create(['unit_id' => $vivo->id, 'quantity' => 5, 'unit_price' => 500, 'discount' => 0, 'subtotal' => 2500]);
    $invoice->update(['subtotal' => 3500, 'grand_total' => 3500, 'paid_amount' => 3500]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertSee($invoice->invoice_no)
        ->assertSee('15/11/2026 10:45')
        ->assertSee('Bismillah Telecom')
        ->assertSee('Chakaria')
        ->assertSee('Samsung Galaxy A15 (2x), Vivo Y28 (5x)')
        ->assertSee('3,500.00')
        ->assertSee('<span class="badge badge-success">Paid</span>', false)
        ->assertSee(route('sales-invoices.show', $invoice))
        ->assertSee(route('sales-invoices.print', $invoice));
});

it('shows walk-in pos invoices without customer or branch', function () {
    actingAsSuperAdmin();

    $unit = stockRowFor('Redmi Note 13');
    $location = Location::factory()->create(['name' => 'Eidgaon Outlet']);

    $invoice = distributorInvoice($unit, [
        'customer_id' => null,
        'location_id' => $location->id,
        'branch_id' => null,
    ]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertSee($invoice->invoice_no)
        ->assertSee('Walk-in')
        ->assertSee('Eidgaon Outlet')
        ->assertSee('Redmi Note 13 (2x)');
});

it('keeps the items summary compact and shows deleted customers by name', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create(['name' => 'Al-Madina Mobile House']);

    $invoice = SalesInvoice::factory()->create([
        'customer_id' => $customer->id,
        'location_id' => Location::factory()->create()->id,
    ]);

    foreach (range(1, 5) as $i) {
        $unit = stockRowFor("Product {$i}");
        $invoice->items()->create(['unit_id' => $unit->id, 'quantity' => 2, 'unit_price' => 500, 'discount' => 0, 'subtotal' => 1000]);
    }

    $invoice->update(['subtotal' => 5000, 'grand_total' => 5000]);

    $customer->delete();

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertSee('Al-Madina Mobile House')
        ->assertSee('Product 1 (2x), Product 2 (2x)')
        ->assertSee('… (+3 more)')
        ->assertDontSee('Product 5');
});

it('displays payment badges and dues for partial-paid, draft and voided invoices', function () {
    actingAsSuperAdmin();

    $unit = stockRowFor('Realme C65');

    $partial = distributorInvoice($unit, ['paid_amount' => 400]);
    $draft = distributorInvoice($unit);
    $voided = distributorInvoice($unit, ['status' => 'voided', 'voided_at' => now()]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertSee('<span class="badge badge-info">Partial</span>', false)
        ->assertSee('600.00')
        ->assertSee('<span class="badge badge-warning">Due</span>', false)
        ->assertSee('<span class="badge badge-danger">Cancelled</span>', false)
        ->assertSee('<span class="block mt-1 text-[10px] font-semibold text-muted">Draft</span>', false)
        ->assertSee('<span class="block mt-1 text-[10px] font-semibold text-danger">Voided</span>', false)
        ->assertSee($partial->invoice_no)
        ->assertSee($draft->invoice_no)
        ->assertSee($voided->invoice_no);
});

it('filters the collections by search, status, payment status and branch', function () {
    actingAsSuperAdmin();

    $branch = Branch::create(['name' => 'Chakaria Branch', 'code' => 'CBR', 'status' => 'active']);
    $branchLocation = Location::factory()->create(['branch_id' => $branch->id]);
    $unit = stockRowFor('Samsung Galaxy A15');

    // POS invoice without a branch of its own still belongs to the branch
    // through its location.
    $pos = distributorInvoice($unit, ['customer_id' => null, 'location_id' => $branchLocation->id, 'branch_id' => null]);
    $other = distributorInvoice($unit, ['location_id' => Location::factory()->create()->id]);
    $paid = distributorInvoice($unit, ['location_id' => $branchLocation->id, 'paid_amount' => 1000]);
    $voided = distributorInvoice($unit, ['location_id' => $branchLocation->id, 'status' => 'voided', 'voided_at' => now()]);

    $this->get(route('collections.index', ['branch_id' => $branch->id]))
        ->assertOk()
        ->assertSee($pos->invoice_no)
        ->assertSee($paid->invoice_no)
        ->assertDontSee($other->invoice_no);

    $this->get(route('collections.index', ['search' => $other->invoice_no]))
        ->assertOk()
        ->assertSee($other->invoice_no)
        ->assertDontSee($pos->invoice_no);

    $this->get(route('collections.index', ['payment_status' => 'paid']))
        ->assertOk()
        ->assertSee($paid->invoice_no)
        ->assertDontSee($pos->invoice_no);

    $this->get(route('collections.index', ['status' => 'voided']))
        ->assertOk()
        ->assertSee($voided->invoice_no)
        ->assertDontSee($pos->invoice_no);
});

it('shows the empty state when no distributor invoices match', function () {
    actingAsSuperAdmin();

    $this->get(route('collections.index', ['search' => 'nothing-matches-this']))
        ->assertOk()
        ->assertSee('No distributor invoices found.');
});

it('paginates the collections list', function () {
    actingAsSuperAdmin();

    $unit = stockRowFor('Samsung Galaxy A15');

    foreach (range(1, 26) as $i) {
        distributorInvoice($unit);
    }

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertSee('Showing', false)
        ->assertSee('<strong>26</strong> records', false);
});
