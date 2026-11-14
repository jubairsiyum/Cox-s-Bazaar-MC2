<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('forbids guests from viewing the suppliers list', function () {
    $this->get(route('suppliers.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing suppliers', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('suppliers.index'))->assertForbidden();
});

it('lists suppliers for a Super Admin', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'name' => 'Subhramaniam',
        'supplier_id' => 'SP0008',
    ]);

    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('Suppliers List')
        ->assertSee('Subhramaniam')
        ->assertSee('SP0008');
});

it('shows the supplier create page', function () {
    actingAsSuperAdmin();

    $this->get(route('suppliers.create'))
        ->assertOk()
        ->assertSee('Suppliers')
        ->assertSee('Add/Update Suppliers')
        ->assertSee('Opening Balance Payments');
});

it('creates a supplier and automatically assigns an SP code', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('suppliers.store'), [
        'name' => 'Krishna Patil',
        'mobile' => '9844545544',
        'email' => 'krishnapatil@gmail.com',
        'phone' => '02987654',
        'gst_number' => 'GSTIN789',
        'tax_number' => 'TAX5544',
        'opening_balance' => 5894.00,
        'country' => 'Bangladesh',
        'city' => "Cox's Bazar",
        'address' => 'Station Road',
    ]);

    $response->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::where('name', 'Krishna Patil')->first();

    expect($supplier)->not->toBeNull()
        ->and($supplier->supplier_id)->toStartWith('SP')
        ->and((float) $supplier->opening_balance)->toBe(5894.00)
        ->and((float) $supplier->purchase_due)->toBe(5894.00);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Supplier::class,
        'auditable_id' => $supplier->id,
    ]);
});

it('fails validation when supplier name is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('suppliers.store'), [
        'name' => '',
        'mobile' => '01700000000',
    ])->assertSessionHasErrors('name');
});

it('updates an existing supplier', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'name' => 'Old Supplier Name',
        'city' => 'Chittagong',
    ]);

    $response = $this->patch(route('suppliers.update', $supplier), [
        'name' => 'Updated Supplier Name',
        'city' => "Cox's Bazar",
        'mobile' => '01899999999',
    ]);

    $response->assertRedirect(route('suppliers.index'));

    expect($supplier->fresh()->name)->toBe('Updated Supplier Name')
        ->and($supplier->fresh()->city)->toBe("Cox's Bazar");

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Supplier::class,
        'auditable_id' => $supplier->id,
    ]);
});

it('soft deletes a supplier', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create(['name' => 'To Delete']);

    $this->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect(route('suppliers.index'));

    expect(Supplier::find($supplier->id))->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'deleted',
        'auditable_type' => Supplier::class,
        'auditable_id' => $supplier->id,
    ]);
});

it('downloads the import template CSV', function () {
    actingAsSuperAdmin();

    $response = $this->get(route('suppliers.import-template'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->getContent())->toContain('Supplier Name')
        ->and($response->getContent())->toContain('Mobile')
        ->and($response->getContent())->toContain('Opening Balance');
});

it('imports suppliers from a CSV file', function () {
    actingAsSuperAdmin();

    $csvContent = "Supplier Name,Mobile,Email,Phone,GST Number,TAX Number,Country Name,State Name,City,Postcode,Address,Opening Balance\n"
        ."Imported Company,01712345678,imported@example.com,023344,GST11,TAX22,Bangladesh,Chittagong,Cox's Bazar,4700,Main Street,2500.00\n";

    $file = UploadedFile::fake()->createWithContent('suppliers.csv', $csvContent);

    $this->post(route('suppliers.import.store'), [
        'file' => $file,
    ])->assertRedirect(route('suppliers.index'));

    $supplier = Supplier::where('email', 'imported@example.com')->first();

    expect($supplier)->not->toBeNull()
        ->and($supplier->name)->toBe('Imported Company')
        ->and((float) $supplier->opening_balance)->toBe(2500.00);
});

it('records a due payment and reduces supplier purchase due', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'purchase_due' => 5000.00,
        'opening_balance' => 5000.00,
    ]);

    $this->post(route('suppliers.pay-due', $supplier), [
        'payment_date' => now()->toDateString(),
        'amount' => 2000.00,
        'payment_type' => 'Cash',
        'payment_note' => 'Partial cash payment',
    ])->assertRedirect();

    expect((float) $supplier->fresh()->purchase_due)->toBe(3000.00);

    $this->assertDatabaseHas('supplier_opening_payments', [
        'supplier_id' => $supplier->id,
        'amount' => 2000.00,
        'payment_type' => 'Cash',
    ]);
});

it('records a return due payment and reduces return due', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'purchase_return_due' => 1500.00,
    ]);

    $this->post(route('suppliers.pay-return-due', $supplier), [
        'payment_date' => now()->toDateString(),
        'amount' => 500.00,
        'payment_type' => 'Bank Transfer',
        'payment_note' => 'Return adjustment',
    ])->assertRedirect();

    expect((float) $supplier->fresh()->purchase_return_due)->toBe(1000.00);

    $this->assertDatabaseHas('supplier_opening_payments', [
        'supplier_id' => $supplier->id,
        'amount' => 500.00,
        'payment_type' => 'Bank Transfer',
    ]);
});

it('deletes an opening payment and reverts supplier due', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'purchase_due' => 3000.00,
    ]);

    $payment = $supplier->openingPayments()->create([
        'payment_date' => now()->toDateString(),
        'amount' => 2000.00,
        'payment_type' => 'Cash',
        'payment_note' => 'Payment to delete',
    ]);

    $this->delete(route('suppliers.payments.destroy', $payment))
        ->assertRedirect();

    expect((float) $supplier->fresh()->purchase_due)->toBe(5000.00);

    $this->assertDatabaseMissing('supplier_opening_payments', [
        'id' => $payment->id,
    ]);
});

it('toggles supplier status between active and inactive', function () {
    actingAsSuperAdmin();

    $supplier = Supplier::factory()->create([
        'status' => 'active',
    ]);

    expect($supplier->isActive())->toBeTrue();

    // Toggle active -> inactive
    $this->patch(route('suppliers.toggle-status', $supplier))
        ->assertRedirect();

    expect($supplier->fresh()->status)->toBe('inactive')
        ->and($supplier->fresh()->isActive())->toBeFalse();

    // Toggle inactive -> active
    $this->patch(route('suppliers.toggle-status', $supplier))
        ->assertRedirect();

    expect($supplier->fresh()->status)->toBe('active')
        ->and($supplier->fresh()->isActive())->toBeTrue();
});

it('forbids unauthorized users from toggling supplier status', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $supplier = Supplier::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->patch(route('suppliers.toggle-status', $supplier))
        ->assertForbidden();

    expect($supplier->fresh()->status)->toBe('active');
});
