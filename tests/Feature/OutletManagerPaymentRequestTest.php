<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

function outletManagerAt(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

it('lets a branch outlet manager open a payment request addressed to their branch', function () {
    $branch = Location::factory()->create();
    $supplier = Supplier::factory()->create();
    $order = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'location_id' => $branch->id]);
    $paymentRequest = SupplierPaymentRequest::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $branch->id,
        'purchase_order_id' => $order->id,
    ]);

    $this->actingAs(outletManagerAt($branch))
        ->get(route('supplier-payments.create', [
            'supplier_id' => $supplier->id,
            'payment_request_id' => $paymentRequest->id,
        ]))
        ->assertOk()
        ->assertSee($order->reference_no);
});

it('does not prefill another branch payment request for an outlet manager', function () {
    $ownBranch = Location::factory()->create();
    $otherBranch = Location::factory()->create();
    $supplier = Supplier::factory()->create();
    $otherOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'location_id' => $otherBranch->id]);
    $otherRequest = SupplierPaymentRequest::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $otherBranch->id,
        'purchase_order_id' => $otherOrder->id,
    ]);

    $this->actingAs(outletManagerAt($ownBranch))
        ->get(route('supplier-payments.create', [
            'supplier_id' => $supplier->id,
            'payment_request_id' => $otherRequest->id,
        ]))
        ->assertOk()
        ->assertDontSee($otherOrder->reference_no);
});
