<?php

declare(strict_types=1);

use App\Enums\PurchaseStatus;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('shows a deleted purchase order on the supplier profile as deleted', function () {
    $supplierUser = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $supplierUser->assignRole('Supplier');
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => Location::factory()->create()->id,
        'purchase_status' => PurchaseStatus::Cancelled,
    ]);
    $order->delete();

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'orders'))
        ->assertOk()
        ->assertSee($order->reference_no)
        ->assertSee('Deleted')
        ->assertSee('carries no due');
});

it('keeps the deleted purchase order out of the supplier due totals', function () {
    $supplierUser = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $supplierUser->assignRole('Supplier');
    $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id]);

    $order = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => Location::factory()->create()->id,
        'grand_total' => 5000,
        'paid_amount' => 0,
        'purchase_status' => PurchaseStatus::Cancelled,
    ]);
    $order->delete();

    $this->actingAs($supplierUser)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk()
        ->assertSee('Grand total of 0 live orders');
});
