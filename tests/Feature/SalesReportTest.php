<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A posted invoice for the report fixtures.
 */
function reportInvoice(float $total, float $paid, array $attributes = []): SalesInvoice
{
    $invoice = SalesInvoice::factory()->posted()->create(array_merge([
        'location_id' => Location::factory()->create()->id,
    ], $attributes));

    $invoice->update([
        'subtotal' => $total,
        'grand_total' => $total,
        'paid_amount' => $paid,
    ]);

    return $invoice->fresh();
}

it('renders the sales report with totals and the payment breakdown', function () {
    actingAsSuperAdmin();

    reportInvoice(1000, 1000);
    $dueInvoice = reportInvoice(500, 0);

    $this->get(route('reports.sales'))
        ->assertOk()
        ->assertSee('Sales Report')
        ->assertSee('Payment Status Breakdown')
        ->assertSee('Document Status Breakdown')
        ->assertSee($dueInvoice->invoice_no);
});

it('filters the report by date range and payment status', function () {
    actingAsSuperAdmin();

    $paid = reportInvoice(1000, 1000);
    $due = reportInvoice(500, 0);

    $this->get(route('reports.sales', ['payment_status' => 'paid']))
        ->assertOk()
        ->assertSee($paid->invoice_no)
        ->assertDontSee($due->invoice_no);

    $this->get(route('reports.sales', ['date_from' => now()->addDay()->toDateString()]))
        ->assertOk()
        ->assertDontSee($paid->invoice_no)
        ->assertDontSee($due->invoice_no);
});

it('filters the report by outlet location', function () {
    actingAsSuperAdmin();

    $mine = reportInvoice(1000, 500, ['branch_id' => null]);
    $other = reportInvoice(700, 700, ['branch_id' => null]);

    $this->get(route('reports.sales', ['location_id' => 999]))
        ->assertOk()
        ->assertDontSee($mine->invoice_no);

    $this->get(route('reports.sales', ['search' => $other->invoice_no]))
        ->assertOk()
        ->assertSee($other->invoice_no);
});

it('scopes the report to the assigned location for outlet managers', function () {
    $mine = Location::factory()->create();
    $other = Location::factory()->create();

    $myInvoice = reportInvoice(1000, 0, ['location_id' => $mine->id]);
    $otherInvoice = reportInvoice(800, 0, ['location_id' => $other->id]);

    $manager = User::factory()->create(['email_verified_at' => now(), 'branch_id' => $mine->id]);
    $manager->assignRole('Outlet Manager');
    $manager->givePermissionTo(['reports.view', 'sales-invoices.view']);

    $this->actingAs($manager)
        ->get(route('reports.sales'))
        ->assertOk()
        ->assertSee($myInvoice->invoice_no)
        ->assertDontSee($otherInvoice->invoice_no);
});

it('blocks users without report permissions', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Storekeeper');

    $this->actingAs($user)->get(route('reports.sales'))->assertForbidden();
});
