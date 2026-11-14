<?php

use App\Helpers\MenuHelper;
use App\Models\Customer;
use App\Models\CustomerCustomField;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('forbids guests from viewing the customers list', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing customers', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
});

it('lists customers for an authorized user', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create([
        'name' => 'Md. Rahim',
        'customer_id' => 'CU0001',
    ]);

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Customers List')
        ->assertSee('Md. Rahim')
        ->assertSee('CU0001');
});

it('shows the customer create page with initially blank custom fields', function () {
    actingAsSuperAdmin();

    $this->get(route('customers.create'))
        ->assertOk()
        ->assertSee('Customers')
        ->assertSee('Add/Update Customers')
        ->assertSee('Custom Fields')
        ->assertSee('No custom fields added yet', false)
        ->assertSee('customFields: []', false)
        ->assertDontSee('Opening Balance Payments');
});

it('creates a customer and automatically assigns a CU code', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('customers.store'), [
        'name' => 'Akbar Ali',
        'mobile' => '01711223344',
        'tel_no' => '034164200',
        'email' => 'akbar@example.com',
        'country' => 'Bangladesh',
        'state' => 'Chittagong',
        'city' => "Cox's Bazar",
        'postcode' => '4700',
        'address' => 'Sea Beach Road',
        'note' => 'VIP Customer',
        'custom_fields' => [
            ['label' => 'NID', 'value' => '19901234567890'],
            ['label' => 'Reference', 'value' => 'Manager Ref'],
        ],
    ]);

    $response->assertRedirect(route('customers.index'));

    $customer = Customer::where('name', 'Akbar Ali')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->customer_id)->toStartWith('CU')
        ->and($customer->mobile)->toBe('01711223344')
        ->and($customer->tel_no)->toBe('034164200')
        ->and($customer->note)->toBe('VIP Customer')
        ->and($customer->custom_fields)->toHaveCount(2)
        ->and($customer->custom_fields[0]['label'])->toBe('NID');

    $this->assertDatabaseHas('customer_custom_fields', [
        'name' => 'NID',
    ]);
    $this->assertDatabaseHas('customer_custom_fields', [
        'name' => 'Reference',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Customer::class,
        'auditable_id' => $customer->id,
    ]);
});

it('fails validation when customer name is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('customers.store'), [
        'name' => '',
        'mobile' => '01700000000',
    ])->assertSessionHasErrors('name');
});

it('fails validation when customer mobile is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('customers.store'), [
        'name' => 'Valid Customer Name',
        'mobile' => '',
    ])->assertSessionHasErrors('mobile');
});

it('shows the customer edit page', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create([
        'name' => 'Existing Customer',
        'city' => "Cox's Bazar",
    ]);

    $this->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertSee('Customers')
        ->assertSee('Add/Update Customers')
        ->assertSee('Existing Customer')
        ->assertSee('Update');
});

it('updates an existing customer', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create([
        'name' => 'Old Customer Name',
        'city' => 'Chittagong',
    ]);

    $response = $this->patch(route('customers.update', $customer), [
        'name' => 'Updated Customer Name',
        'city' => "Cox's Bazar",
        'mobile' => '01899999999',
        'note' => 'Updated note',
    ]);

    $response->assertRedirect(route('customers.index'));

    $customer->refresh();
    expect($customer->name)->toBe('Updated Customer Name')
        ->and($customer->city)->toBe("Cox's Bazar")
        ->and($customer->mobile)->toBe('01899999999');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Customer::class,
        'auditable_id' => $customer->id,
    ]);
});

it('deletes a customer via soft delete', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create([
        'name' => 'Customer To Delete',
    ]);

    $response = $this->delete(route('customers.destroy', $customer));

    $response->assertRedirect(route('customers.index'));

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'deleted',
        'auditable_type' => Customer::class,
        'auditable_id' => $customer->id,
    ]);
});

it('toggles customer status between active and inactive', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create([
        'status' => 'active',
    ]);

    $this->patch(route('customers.toggle-status', $customer))->assertRedirect();

    $customer->refresh();
    expect($customer->status)->toBe('inactive');

    $this->patch(route('customers.toggle-status', $customer))->assertRedirect();

    $customer->refresh();
    expect($customer->status)->toBe('active');
});

it('redirects create route with customer ID query to edit route', function () {
    actingAsSuperAdmin();

    $customer = Customer::factory()->create();

    $this->get(route('customers.create', ['customer_id' => $customer->customer_id]))
        ->assertRedirect(route('customers.edit', $customer));
});

it('renders the customer PDF view', function () {
    actingAsSuperAdmin();

    Customer::factory()->create(['name' => 'PDF Customer Test']);

    $this->get(route('customers.pdf-view'))
        ->assertOk()
        ->assertSee('Customers List')
        ->assertSee('PDF Customer Test')
        ->assertSee('Download PDF');
});

it('renders the customer print view', function () {
    actingAsSuperAdmin();

    Customer::factory()->create(['name' => 'Print Customer Test']);

    $this->get(route('customers.print-view'))
        ->assertOk()
        ->assertSee('Customers List')
        ->assertSee('Print Customer Test')
        ->assertSee('window.print()', false);
});

it('exports customers to excel and csv', function () {
    actingAsSuperAdmin();

    Customer::factory()->count(2)->create();

    $xlsxResponse = $this->get(route('customers.export', ['format' => 'xlsx']));
    $xlsxResponse->assertOk()
        ->assertHeader('content-disposition');

    $csvResponse = $this->get(route('customers.export', ['format' => 'csv']));
    $csvResponse->assertOk()
        ->assertHeader('content-disposition');
});

it('downloads the customer import CSV template', function () {
    actingAsSuperAdmin();

    $response = $this->get(route('customers.import-template'));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="customer_import_template.csv"')
        ->assertSee('Customer Name')
        ->assertSee('Mobile No')
        ->assertSee('Tel No')
        ->assertSee('Rahim Uddin');
});

it('imports customers from a valid CSV file', function () {
    actingAsSuperAdmin();

    $csvContent = implode("\n", [
        'Customer Name,Mobile No,Tel No,Email,Country Name,State Name,City,Postcode,Address,Note',
        'Imported Customer 1,01700000001,034100001,imp1@example.com,Bangladesh,Chittagong,Cox\'s Bazar,4700,Hotel Motel Zone,First imported note',
        'Imported Customer 2,01700000002,034100002,imp2@example.com,Bangladesh,Chittagong,Cox\'s Bazar,4700,Main Road,Second imported note',
    ]);

    $file = UploadedFile::fake()->createWithContent('customers.csv', $csvContent);

    $response = $this->post(route('customers.import.store'), [
        'file' => $file,
    ]);

    $response->assertRedirect(route('customers.index'));
    $response->assertSessionHas('success', 'Successfully imported 2 customer(s).');

    expect(Customer::where('name', 'Imported Customer 1')->exists())->toBeTrue()
        ->and(Customer::where('name', 'Imported Customer 2')->exists())->toBeTrue();
});

it('verifies menu items for customers in MenuHelper', function () {
    $mainNav = MenuHelper::getMainNavItems();

    $customerNav = collect($mainNav)->firstWhere('name', 'Customers');

    expect($customerNav)->not->toBeNull()
        ->and($customerNav['subItems'])->toHaveCount(3)
        ->and($customerNav['subItems'][0]['name'])->toBe('New Customer')
        ->and($customerNav['subItems'][0]['path'])->toBe('/customers/create')
        ->and($customerNav['subItems'][1]['name'])->toBe('Customers List')
        ->and($customerNav['subItems'][1]['path'])->toBe('/customers')
        ->and($customerNav['subItems'][2]['name'])->toBe('Import Customers')
        ->and($customerNav['subItems'][2]['path'])->toBe('/customers/import');
});

it('dynamically adds and deletes custom field definitions in the database', function () {
    actingAsSuperAdmin();

    $response = $this->postJson(route('customers.custom-fields.store'), [
        'name' => 'Trade License',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'custom_field' => [
                'name' => 'Trade License',
                'field_key' => 'trade_license',
            ],
        ]);

    $this->assertDatabaseHas('customer_custom_fields', [
        'name' => 'Trade License',
        'field_key' => 'trade_license',
    ]);

    $field = CustomerCustomField::where('name', 'Trade License')->first();

    $deleteResponse = $this->deleteJson(route('customers.custom-fields.destroy', $field));
    $deleteResponse->assertOk()->assertJson(['success' => true]);

    $this->assertDatabaseMissing('customer_custom_fields', [
        'name' => 'Trade License',
    ]);
});
