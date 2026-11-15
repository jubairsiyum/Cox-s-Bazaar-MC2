<?php

use App\Enums\StockMovementType;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('forbids guests from viewing the units list', function () {
    $this->get(route('units.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing units', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('units.index'))->assertForbidden();
});

it('lists quantity entries for a Super Admin', function () {
    actingAsSuperAdmin();

    Unit::factory()->create([
        'product_name' => 'Phone Charger 20W',
        'quantity' => 50,
        'unit' => 'Pcs',
    ]);

    $this->get(route('units.index'))
        ->assertOk()
        ->assertSee('Units')
        ->assertSee('Manage your quantity data')
        ->assertSee('All your units')
        ->assertSee('Phone Charger 20W')
        ->assertSee('Pcs');
});

it('shows the add quantity modal on the units list page', function () {
    actingAsSuperAdmin();

    $this->get(route('units.index'))
        ->assertOk()
        ->assertSee('Add quantity')
        ->assertSee('Product name')
        ->assertSee('Quantity')
        ->assertSee('Unit')
        ->assertSee('Save');
});

it('creates a quantity entry from the add quantity form', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('units.store'), [
        'product_name' => 'Tempered Glass Screen Protector',
        'quantity' => 25,
        'unit' => 'Box',
        'note' => '10 pcs per box',
    ]);

    $response->assertRedirect(route('units.index'));

    $unit = Unit::where('product_name', 'Tempered Glass Screen Protector')->first();

    expect($unit)->not->toBeNull()
        ->and((float) $unit->quantity)->toBe(25.0)
        ->and($unit->unit)->toBe('Box')
        ->and($unit->note)->toBe('10 pcs per box');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Unit::class,
        'auditable_id' => $unit->id,
    ]);
});

it('fails validation when product name is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('units.store'), [
        'product_name' => '',
        'quantity' => 10,
        'unit' => 'Pcs',
    ])->assertSessionHasErrors('product_name');
});

it('fails validation when quantity is missing or invalid', function () {
    actingAsSuperAdmin();

    $this->post(route('units.store'), [
        'product_name' => 'Power Bank',
        'quantity' => '',
        'unit' => 'Pcs',
    ])->assertSessionHasErrors('quantity');

    $this->post(route('units.store'), [
        'product_name' => 'Power Bank',
        'quantity' => -5,
        'unit' => 'Pcs',
    ])->assertSessionHasErrors('quantity');
});

it('fails validation when quantity is not a whole number', function () {
    actingAsSuperAdmin();

    $this->post(route('units.store'), [
        'product_name' => 'Power Bank',
        'quantity' => 2.5,
        'unit' => 'Pcs',
    ])->assertSessionHasErrors('quantity');
});

it('displays quantities as whole numbers without decimals', function () {
    actingAsSuperAdmin();

    Unit::factory()->create([
        'product_name' => 'Phone Charger 20W',
        'quantity' => 50,
        'unit' => 'Pcs',
    ]);

    $this->get(route('units.index'))
        ->assertOk()
        ->assertSee('Phone Charger 20W')
        ->assertSee('50')
        ->assertDontSee('50.00');
});

it('fails validation when unit is not a standard unit', function () {
    actingAsSuperAdmin();

    $this->post(route('units.store'), [
        'product_name' => 'Bluetooth Earbuds',
        'quantity' => 5,
        'unit' => 'Barrel',
    ])->assertSessionHasErrors('unit');
});

it('updates an existing quantity entry', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create([
        'product_name' => 'Memory Card 64GB',
        'quantity' => 100,
        'unit' => 'Pcs',
    ]);

    $response = $this->patch(route('units.update', $unit), [
        'product_name' => 'Memory Card 64GB',
        'quantity' => 150,
        'unit' => 'Box',
        'note' => 'Updated note',
    ]);

    $response->assertRedirect(route('units.index'));

    expect((float) $unit->fresh()->quantity)->toBe(150.0)
        ->and($unit->fresh()->unit)->toBe('Box')
        ->and($unit->fresh()->note)->toBe('Updated note');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Unit::class,
        'auditable_id' => $unit->id,
    ]);
});

it('soft deletes a quantity entry', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['product_name' => 'To Delete']);

    $this->delete(route('units.destroy', $unit))
        ->assertRedirect(route('units.index'));

    expect(Unit::find($unit->id))->toBeNull()
        ->and(Unit::withTrashed()->find($unit->id))->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'deleted',
        'auditable_type' => Unit::class,
        'auditable_id' => $unit->id,
    ]);
});

it('forbids unauthorized users from deleting a quantity entry', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $unit = Unit::factory()->create(['product_name' => 'Protected']);

    $this->actingAs($user)
        ->delete(route('units.destroy', $unit))
        ->assertForbidden();

    expect(Unit::find($unit->id))->not->toBeNull();
});

it('downloads the sample CSV with the required columns', function () {
    actingAsSuperAdmin();

    $response = $this->get(route('units.import-template'));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="unit_import_template.csv"');

    expect($response->getContent())->toContain('Product Name')
        ->and($response->getContent())->toContain('Quantity')
        ->and($response->getContent())->toContain('Unit')
        ->and($response->getContent())->toContain('Note')
        ->and($response->getContent())->toContain('Screen Protector');
});

it('shows the import quantities page with the sample download option', function () {
    actingAsSuperAdmin();

    $this->get(route('units.import'))
        ->assertOk()
        ->assertSee('Import Quantities')
        ->assertSee('Import Instructions')
        ->assertSee('Download Example Format');
});

it('imports quantity entries from a CSV file', function () {
    actingAsSuperAdmin();

    $csvContent = implode("\n", [
        'Product Name,Quantity,Unit,Note',
        'USB Type-C Cable,20,Box,12 pcs per box',
        'Phone Charger 20W,50,pcs,Fast chargers',
    ]);

    $file = UploadedFile::fake()->createWithContent('units.csv', $csvContent);

    $response = $this->post(route('units.import.store'), [
        'file' => $file,
    ]);

    $response->assertRedirect(route('units.index'));
    $response->assertSessionHas('success', 'Successfully imported 2 quantity entries.');

    $cable = Unit::where('product_name', 'USB Type-C Cable')->first();

    expect($cable)->not->toBeNull()
        ->and($cable->unit)->toBe('Box')
        ->and((float) $cable->quantity)->toBe(20.0)
        ->and(Unit::where('product_name', 'Phone Charger 20W')->first()->unit)->toBe('Pcs');
});

it('renders the pdf preview page with quantity entries and headers', function () {
    actingAsSuperAdmin();

    Unit::factory()->create([
        'product_name' => 'Bluetooth Earbuds',
        'quantity' => 6,
        'unit' => 'Box',
    ]);

    $this->get(route('units.pdf-view', ['columns' => 'product_name,quantity,unit']))
        ->assertOk()
        ->assertSee('Units List')
        ->assertSee('Bluetooth Earbuds')
        ->assertSee('Box')
        ->assertSee('Download / Save as PDF');
});

it('renders the print preview page with quantity entries and grid table', function () {
    actingAsSuperAdmin();

    Unit::factory()->create(['product_name' => 'Bluetooth Earbuds']);

    $this->get(route('units.print-view', ['columns' => 'product_name,quantity,unit']))
        ->assertOk()
        ->assertSee('Units List')
        ->assertSee('Bluetooth Earbuds')
        ->assertSee('window.print()', false);
});

it('exports quantity entries with selected columns to excel or csv', function () {
    actingAsSuperAdmin();

    Unit::factory()->create(['product_name' => 'Export Product']);

    $response = $this->get(route('units.export', [
        'format' => 'csv',
        'columns' => 'product_name,quantity,unit',
    ]));

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

it('searches quantity entries by product name and unit', function () {
    actingAsSuperAdmin();

    Unit::factory()->create(['product_name' => 'Power Bank 10000mAh', 'unit' => 'Box']);
    Unit::factory()->create(['product_name' => 'Smart Watch Band', 'unit' => 'Dozen']);

    $this->get(route('units.index', ['search' => 'Power Bank']))
        ->assertOk()
        ->assertSee('Power Bank 10000mAh')
        ->assertDontSee('Smart Watch Band');
});

it('sorts quantity entries by product name', function () {
    actingAsSuperAdmin();

    Unit::factory()->create(['product_name' => 'Vivo Phone Case']);
    Unit::factory()->create(['product_name' => 'Apple Phone Case']);

    $response = $this->get(route('units.index', ['sort' => 'product_name', 'direction' => 'asc']));
    $response->assertOk();

    expect(mb_strpos($response->getContent(), 'Apple Phone Case'))->toBeLessThan(
        mb_strpos($response->getContent(), 'Vivo Phone Case')
    );
});

it('records an opening movement when a quantity entry is created', function () {
    actingAsSuperAdmin();

    $this->post(route('units.store'), [
        'product_name' => 'Phone Charger 20W',
        'quantity' => 25,
        'unit' => 'Pcs',
    ]);

    $unit = Unit::where('product_name', 'Phone Charger 20W')->first();

    expect($unit->stockMovements()->count())->toBe(1)
        ->and($unit->stockMovements()->first()->type)->toBe(StockMovementType::Opening)
        ->and($unit->stockMovements()->first()->quantity)->toBe(25);
});

it('records an adjustment movement when the quantity is edited through the form', function () {
    actingAsSuperAdmin();

    $unit = Unit::factory()->create(['product_name' => 'Memory Card 64GB', 'quantity' => 100, 'unit' => 'Pcs']);

    $this->patch(route('units.update', $unit), [
        'product_name' => 'Memory Card 64GB',
        'quantity' => 80,
        'unit' => 'Pcs',
        'note' => 'Stock count correction',
    ]);

    $movement = $unit->fresh()->stockMovements()->latest('id')->first();

    expect((int) $unit->fresh()->quantity)->toBe(80)
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(-20)
        ->and($movement->quantity_after)->toBe(80)
        ->and((int) $unit->fresh()->stockMovements()->sum('quantity'))->toBe(80);
});

it('records an adjustment movement when stock is changed outside the stock service', function () {
    $unit = Unit::factory()->create(['quantity' => 10, 'unit' => 'Pcs']);

    $unit->update(['quantity' => 4]);

    $movement = $unit->fresh()->stockMovements()->latest('id')->first();

    expect((int) $unit->fresh()->quantity)->toBe(4)
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(-6);
});

it('flags low stock products using the low stock threshold setting', function () {
    $low = Unit::factory()->create(['product_name' => 'Low Stock Item', 'quantity' => 3]);
    $healthy = Unit::factory()->create(['product_name' => 'Healthy Stock Item', 'quantity' => 50]);

    expect($low->isLowStock())->toBeTrue()
        ->and($healthy->isLowStock())->toBeFalse();

    Setting::updateOrCreate(['key' => 'low_stock_threshold'], ['value' => '2', 'group' => 'system']);

    expect($low->fresh()->isLowStock())->toBeFalse();
});

it('shows a low stock badge on the units list for products below the threshold', function () {
    actingAsSuperAdmin();

    Unit::factory()->create(['product_name' => 'Almost Gone Item', 'quantity' => 2]);

    $this->get(route('units.index'))
        ->assertOk()
        ->assertSee('Almost Gone Item')
        ->assertSee('LOW STOCK');
});
