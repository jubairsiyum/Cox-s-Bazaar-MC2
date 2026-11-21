<?php

declare(strict_types=1);

use App\Enums\SupplierPaymentRequestStatus;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified, active Supplier-role login linked to its own supplier profile.
 *
 * @return array{0: User, 1: Supplier}
 */
function portalEditLogin(array $supplierAttributes = []): array
{
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');

    return [$user, Supplier::factory()->create(array_merge(['user_id' => $user->id], $supplierAttributes))];
}

/**
 * A purchase invoice from the supplier to the branch that leaves the given due.
 */
function portalEditDue(Supplier $supplier, Location $branch, float $due = 1000): PurchaseOrder
{
    return PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'location_id' => $branch->id,
        'grand_total' => $due,
        'paid_amount' => 0,
    ]);
}

/**
 * The valid details payload a supplier submits from the Edit Details form.
 *
 * @return array<string, mixed>
 */
function portalEditDetails(array $overrides = []): array
{
    return array_merge([
        'name' => 'Renamed Telecom',
        'tax_number' => 'TAX-RENAMED-01',
        'mobile' => '01711111111',
        'tel_no' => '021000111',
        'email' => 'contact@renamed.test',
        'country' => 'Bangladesh',
        'state' => 'Dhaka',
        'city' => 'Dhaka',
        'postcode' => '1000',
        'address' => 'New Road, Dhaka',
    ], $overrides);
}

it('saves the editable details of the linked supplier', function () {
    [$user, $supplier] = portalEditLogin();

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $supplier->refresh();

    expect($supplier)
        ->name->toBe('Renamed Telecom')
        ->tax_number->toBe('TAX-RENAMED-01')
        ->mobile->toBe('01711111111')
        ->tel_no->toBe('021000111')
        ->email->toBe('contact@renamed.test')
        ->city->toBe('Dhaka')
        ->address->toBe('New Road, Dhaka');

    expect(AuditLog::query()
        ->where('auditable_type', Supplier::class)
        ->where('auditable_id', $supplier->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();
});

it('ignores status, opening balance, ledger, code and link fields posted with the details', function () {
    [$user, $supplier] = portalEditLogin(['opening_balance' => 250, 'purchase_due' => 900, 'purchase_return_due' => 40, 'status' => 'active']);
    $originalCode = $supplier->supplier_id;
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails([
            'status' => 'inactive',
            'opening_balance' => 99999,
            'purchase_due' => 1,
            'purchase_return_due' => 2,
            'supplier_id' => 'SP9999',
            'user_id' => $otherUser->id,
        ]))
        ->assertSessionHasNoErrors();

    $supplier->refresh();

    expect($supplier)
        ->name->toBe('Renamed Telecom')
        ->status->toBe('active')
        ->supplier_id->toBe($originalCode)
        ->user_id->toBe($user->id);

    expect((float) $supplier->opening_balance)->toBe(250.0)
        ->and((float) $supplier->purchase_due)->toBe(900.0)
        ->and((float) $supplier->purchase_return_due)->toBe(40.0);
});

it('keeps the supplier login email unchanged when the contact email changes', function () {
    [$user, $supplier] = portalEditLogin();
    $loginEmail = $user->email;

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails(['email' => 'billing@renamed.test']))
        ->assertSessionHasNoErrors();

    expect($supplier->fresh()->email)->toBe('billing@renamed.test')
        ->and($user->fresh()->email)->toBe($loginEmail);
});

it('keeps the supplier own email and tax number without reporting them as taken', function () {
    [$user, $supplier] = portalEditLogin();

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails([
            'email' => $supplier->email,
            'tax_number' => $supplier->tax_number,
        ]))
        ->assertSessionHasNoErrors();
});

it('rejects an email or tax number already used by another supplier', function () {
    [$user, $supplier] = portalEditLogin();
    Supplier::factory()->create(['email' => 'taken@other.test', 'tax_number' => 'TAX-TAKEN-99']);

    $this->actingAs($user)
        ->from(route('suppliers.my-profile.section', 'details'))
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails([
            'email' => 'taken@other.test',
            'tax_number' => 'TAX-TAKEN-99',
        ]))
        ->assertRedirect(route('suppliers.my-profile.section', 'details'))
        ->assertSessionHasErrors(['email', 'tax_number'], errorBag: 'updateSupplierDetails');

    expect($supplier->fresh()->email)->not->toBe('taken@other.test');
});

it('requires a supplier name and mobile number', function () {
    [$user, $supplier] = portalEditLogin();

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails(['name' => '', 'mobile' => '']))
        ->assertSessionHasErrors(['name', 'mobile'], errorBag: 'updateSupplierDetails');
});

it('does not let a supplier edit another supplier profile', function () {
    [$user] = portalEditLogin();
    $otherSupplier = Supplier::factory()->create(['name' => 'Beta Mobiles']);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $otherSupplier), portalEditDetails())
        ->assertNotFound();

    expect($otherSupplier->fresh()->name)->toBe('Beta Mobiles');
});

it('refuses the details update to a login without a linked supplier profile', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
    $user->assignRole('Supplier');
    $supplier = Supplier::factory()->create(['name' => 'Unlinked Target']);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.update', $supplier), portalEditDetails())
        ->assertForbidden();

    expect($supplier->fresh()->name)->toBe('Unlinked Target');
});

it('keeps every other supplier write denied to a supplier login', function () {
    [$user, $supplier] = portalEditLogin();

    $this->actingAs($user)
        ->patch(route('suppliers.update', $supplier), portalEditDetails())
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('suppliers.toggle-status', $supplier))
        ->assertForbidden();

    expect($user->can('update', $supplier))->toBeFalse()
        ->and($user->can('delete', $supplier))->toBeFalse()
        ->and($user->can('updateOwnProfile', $supplier))->toBeTrue();
});

it('changes the login password when the current password is correct', function () {
    [$user] = portalEditLogin();
    $user->update(['password' => Hash::make('Old-Secret-2026!')]);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.password'), [
            'current_password' => 'Old-Secret-2026!',
            'password' => 'Fresh-Secret-2026!',
            'password_confirmation' => 'Fresh-Secret-2026!',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Hash::check('Fresh-Secret-2026!', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change with a wrong current password', function () {
    [$user] = portalEditLogin();
    $user->update(['password' => Hash::make('Old-Secret-2026!')]);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.password'), [
            'current_password' => 'Wrong-Secret-2026!',
            'password' => 'Fresh-Secret-2026!',
            'password_confirmation' => 'Fresh-Secret-2026!',
        ])
        ->assertSessionHasErrors('current_password', errorBag: 'updateSupplierPassword');

    expect(Hash::check('Old-Secret-2026!', $user->fresh()->password))->toBeTrue();
});

it('edits an open payment request the supplier sent', function () {
    [$user, $supplier] = portalEditLogin();
    $branch = Location::factory()->create();
    $order = portalEditDue($supplier, $branch, 1000);
    $paymentRequest = SupplierPaymentRequest::factory()->forOrder($order)->create([
        'message' => 'Original message',
        'amount' => 500,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.requests.update', $paymentRequest), [
            'message' => 'Please settle the revised amount.',
            'amount' => 300,
            'purchase_order_id' => $order->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $paymentRequest->refresh();

    expect($paymentRequest)
        ->message->toBe('Please settle the revised amount.')
        ->purchase_order_id->toBe($order->id)
        ->location_id->toBe($branch->id)
        ->status->toBe(SupplierPaymentRequestStatus::Open);

    expect((float) $paymentRequest->amount)->toBe(300.0);
});

it('applies the due rules to an edited payment request amount', function () {
    [$user, $supplier] = portalEditLogin();
    $branch = Location::factory()->create();
    $order = portalEditDue($supplier, $branch, 1000);
    $paymentRequest = SupplierPaymentRequest::factory()->forOrder($order)->create([
        'message' => 'Original message',
        'amount' => 500,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.requests.update', $paymentRequest), [
            'message' => 'Too much',
            'amount' => 5000,
            'purchase_order_id' => $order->id,
        ])
        ->assertSessionHasErrors('amount', errorBag: 'updatePaymentRequest-'.$paymentRequest->id);

    expect((float) $paymentRequest->fresh()->amount)->toBe(500.0);
});

it('refuses to edit an actioned or cancelled payment request', function (string $state) {
    [$user, $supplier] = portalEditLogin();
    $branch = Location::factory()->create();
    $order = portalEditDue($supplier, $branch, 1000);
    $paymentRequest = SupplierPaymentRequest::factory()->forOrder($order)->{$state}()->create([
        'message' => 'Locked message',
        'amount' => 500,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.requests.update', $paymentRequest), [
            'message' => 'Changed after closing',
            'amount' => 200,
            'purchase_order_id' => $order->id,
        ])
        ->assertForbidden();

    expect($paymentRequest->fresh())
        ->message->toBe('Locked message');
})->with(['actioned', 'cancelled']);

it('does not let a supplier edit another supplier payment request', function () {
    [$user] = portalEditLogin();
    $otherSupplier = Supplier::factory()->create();
    $otherOrder = portalEditDue($otherSupplier, Location::factory()->create(), 1000);
    $otherRequest = SupplierPaymentRequest::factory()->forOrder($otherOrder)->create([
        'message' => 'Other supplier message',
        'amount' => 500,
    ]);

    $this->actingAs($user)
        ->patch(route('suppliers.my-profile.requests.update', $otherRequest), [
            'message' => 'Hijacked',
            'amount' => 100,
        ])
        ->assertNotFound();

    expect($otherRequest->fresh()->message)->toBe('Other supplier message');
});

it('shows the edit details and change password actions only to the supplier login', function () {
    [$user, $supplier] = portalEditLogin();

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'details'))
        ->assertOk()
        ->assertSee('Edit Details')
        ->assertSee(route('suppliers.my-profile.update', $supplier), false)
        ->assertSee('Change Password')
        ->assertSee(route('suppliers.my-profile.password'), false);

    actingAsSuperAdmin();

    $this->get(route('suppliers.profile', $supplier))
        ->assertOk()
        ->assertDontSee('Edit Details')
        ->assertDontSee(route('suppliers.my-profile.update', $supplier), false)
        ->assertDontSee('Change Password');
});

it('shows the edit action on open payment requests only', function () {
    [$user, $supplier] = portalEditLogin();
    $branch = Location::factory()->create();
    $order = portalEditDue($supplier, $branch, 1000);

    $open = SupplierPaymentRequest::factory()->forOrder($order)->create(['message' => 'Open request', 'created_by' => $user->id]);
    $actioned = SupplierPaymentRequest::factory()->forOrder($order)->actioned()->create(['message' => 'Actioned request', 'created_by' => $user->id]);
    $cancelled = SupplierPaymentRequest::factory()->forOrder($order)->cancelled()->create(['message' => 'Cancelled request', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('suppliers.my-profile.section', 'requests'))
        ->assertOk()
        ->assertSee(route('suppliers.my-profile.requests.update', $open), false)
        ->assertDontSee(route('suppliers.my-profile.requests.update', $actioned), false)
        ->assertDontSee(route('suppliers.my-profile.requests.update', $cancelled), false)
        ->assertSee('Cancel Request');
});
