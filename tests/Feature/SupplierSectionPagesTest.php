<?php

declare(strict_types=1);

use App\Enums\PurchaseStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * @return array{0: User, 1: Supplier}
 */
function sectionPagesSupplierLogin(): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');

    return [$user, Supplier::factory()->create(['user_id' => $user->id])];
}

/**
 * @return array<string, string>
 */
function sectionPagesHeadings(): array
{
    return [
        'details' => 'Overview',
        'dues' => 'Branch Dues',
        'orders' => 'Purchase Orders',
        'payments' => 'Payments',
        'returns' => 'Returns',
        'requests' => 'Payment Requests',
        'activity' => 'Activity',
    ];
}

it('redirects the profile link to the details page', function () {
    [$user] = sectionPagesSupplierLogin();

    $this->actingAs($user)
        ->get(route('suppliers.my-profile'))
        ->assertRedirect(route('suppliers.my-profile.section', 'details'));
});

it('opens every section page under its own heading', function () {
    [$user] = sectionPagesSupplierLogin();

    foreach (sectionPagesHeadings() as $section => $label) {
        $this->actingAs($user)
            ->get(route('suppliers.my-profile.section', $section))
            ->assertOk()
            ->assertSee('>'.$label.'</h1>', false);
    }
});

it('keeps the purchase order and payment request content off the dues page', function () {
    [$user, $supplier] = sectionPagesSupplierLogin();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => Location::factory()->create()->id,
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);
    SupplierPaymentRequest::factory()->forOrder($order)->create(['message' => 'Settle the Chakaria balance now.']);

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'dues'))
        ->assertOk()
        ->assertSee('Branch-wise Dues')
        ->assertDontSee('Settle the Chakaria balance now.')
        ->assertDontSee('Send Payment Request')
        ->assertDontSee('request_message')
        ->assertViewMissing('orders')
        ->assertViewMissing('paymentRequests');
});

it('shows a deleted purchase order on the purchase orders page as deleted', function () {
    [$user, $supplier] = sectionPagesSupplierLogin();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => Location::factory()->create()->id,
        'purchase_status' => PurchaseStatus::Cancelled,
    ]);
    $order->delete();

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'orders'))
        ->assertOk()
        ->assertSee($order->reference_no)
        ->assertSee('Deleted')
        ->assertSee('carries no due')
        ->assertDontSee('Send Payment Request')
        ->assertViewMissing('branches');
});

it('shows only the payments of the supplier on the payments page', function () {
    [$user, $supplier] = sectionPagesSupplierLogin();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => Location::factory()->create()->id,
        'grand_total' => 1000,
    ]);
    PurchasePayment::factory()->approved()->create(['purchase_order_id' => $order->id, 'amount' => 400]);

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'payments'))
        ->assertOk()
        ->assertViewHas('payments', fn (array $payments) => $payments['invoice_total'] === 400.0)
        ->assertDontSee('Send Payment Request')
        ->assertViewMissing('orders');
});

it('shows the summary cards and the details on the details page only', function () {
    [$user] = sectionPagesSupplierLogin();

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk()
        ->assertSee('Grand total of')
        ->assertViewHas('summary')
        ->assertViewMissing('feed')
        ->assertViewMissing('branches');
});

it('lists the seven profile sections as sidebar pages for a supplier login without My Profile', function () {
    [$user] = sectionPagesSupplierLogin();

    $response = $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'dues'))
        ->assertOk();

    $sidebar = Str::between($response->getContent(), '<aside', '</aside>');

    expect($sidebar)
        ->not->toContain('My Profile')
        ->toContain('Notifications')
        ->toContain("isActive('/my-supplier-profile/dues')");

    foreach (array_keys(sectionPagesHeadings()) as $section) {
        expect($sidebar)->toContain('href="/my-supplier-profile/'.$section.'"');
    }
});

it('refuses every section page to a supplier login without a linked supplier profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');

    foreach (array_keys(sectionPagesHeadings()) as $section) {
        $this->actingAs($user)
            ->get(route('suppliers.my-profile.section', $section))
            ->assertForbidden();
    }
});

it('does not open an unknown profile section', function () {
    [$user] = sectionPagesSupplierLogin();

    $this->actingAs($user)
        ->get('/my-supplier-profile/unknown')
        ->assertNotFound();
});

it('still renders every section on the staff supplier profile', function () {
    $admin = actingAsSuperAdmin();
    $supplier = Supplier::factory()->create();

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => Location::factory()->create()->id,
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);
    SupplierPaymentRequest::factory()->forOrder($order)->create(['message' => 'Staff sees this request.']);

    $response = $this->actingAs($admin)
        ->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertSee('Branch-wise Dues')
        ->assertSee('Send Payment Request')
        ->assertSee('Staff sees this request.')
        ->assertSee('Notifications &amp; Activity', false);

    foreach (array_keys(sectionPagesHeadings()) as $anchor) {
        $response->assertSee('id="'.$anchor.'"', false);
    }
});
