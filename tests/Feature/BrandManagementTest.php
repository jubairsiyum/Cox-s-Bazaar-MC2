<?php

use App\Models\Brand;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('forbids guests from viewing the brands list', function () {
    $this->get(route('brands.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing brands', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('brands.index'))->assertForbidden();
});

it('hides the brands action column for users without manage permission', function () {
    $salesStaff = User::factory()->create(['email_verified_at' => now()]);
    $salesStaff->assignRole('Sales Staff');

    Brand::factory()->create(['name' => 'Samsung']);

    $this->actingAs($salesStaff)->get(route('brands.index'))
        ->assertOk()
        ->assertSee('Samsung')
        ->assertDontSee('>Action</th>', false);
});

it('shows the brands action column for users with manage permission', function () {
    actingAsSuperAdmin();

    Brand::factory()->create(['name' => 'Samsung']);

    $this->get(route('brands.index'))
        ->assertSee('>Action</th>', false);
});

it('lists brands for a Super Admin', function () {
    actingAsSuperAdmin();

    Brand::factory()->create([
        'name' => 'Samsung',
        'note' => 'Samsung Galaxy smartphones and tablets',
    ]);

    $this->get(route('brands.index'))
        ->assertOk()
        ->assertSee('Brands')
        ->assertSee('Manage your brands')
        ->assertSee('All your brands')
        ->assertSee('Samsung')
        ->assertSee('Samsung Galaxy smartphones and tablets');
});

it('shows the add brand modal on the brands list page', function () {
    actingAsSuperAdmin();

    $this->get(route('brands.index'))
        ->assertOk()
        ->assertSee('Add brand')
        ->assertSee('Brand name')
        ->assertSee('Short description')
        ->assertSee('Save');
});

it('creates a brand from the add brand form', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('brands.store'), [
        'name' => 'OnePlus',
        'note' => 'OnePlus flagship smartphones',
    ]);

    $response->assertRedirect(route('brands.index'));

    $brand = Brand::where('name', 'OnePlus')->first();

    expect($brand)->not->toBeNull()
        ->and($brand->note)->toBe('OnePlus flagship smartphones');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Brand::class,
        'auditable_id' => $brand->id,
    ]);
});

it('fails validation when brand name is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('brands.store'), [
        'name' => '',
        'note' => 'Some note',
    ])->assertSessionHasErrors('name');
});

it('fails validation when brand name is duplicated', function () {
    actingAsSuperAdmin();

    Brand::factory()->create(['name' => 'Nokia']);

    $this->post(route('brands.store'), [
        'name' => 'Nokia',
    ])->assertSessionHasErrors('name');
});

it('restores an archived brand when the same name is used again', function () {
    actingAsSuperAdmin();

    $brand = Brand::factory()->create(['name' => 'Nokia']);
    $brand->delete();

    $this->post(route('brands.store'), [
        'name' => 'Nokia',
        'note' => 'Back in the catalogue',
    ])->assertRedirect(route('brands.index'));

    $restored = Brand::where('name', 'Nokia')->first();

    expect($restored)->not->toBeNull()
        ->and($restored->id)->toBe($brand->id)
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->note)->toBe('Back in the catalogue')
        ->and(Brand::withTrashed()->where('name', 'Nokia')->count())->toBe(1);
});

it('updates an existing brand', function () {
    actingAsSuperAdmin();

    $brand = Brand::factory()->create([
        'name' => 'Old Brand Name',
        'note' => 'Old note',
    ]);

    $response = $this->patch(route('brands.update', $brand), [
        'name' => 'Updated Brand Name',
        'note' => 'New note',
    ]);

    $response->assertRedirect(route('brands.index'));

    expect($brand->fresh()->name)->toBe('Updated Brand Name')
        ->and($brand->fresh()->note)->toBe('New note');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Brand::class,
        'auditable_id' => $brand->id,
    ]);
});

it('soft deletes a brand', function () {
    actingAsSuperAdmin();

    $brand = Brand::factory()->create(['name' => 'To Delete']);

    $this->delete(route('brands.destroy', $brand))
        ->assertRedirect(route('brands.index'));

    expect(Brand::find($brand->id))->toBeNull()
        ->and(Brand::withTrashed()->find($brand->id))->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'deleted',
        'auditable_type' => Brand::class,
        'auditable_id' => $brand->id,
    ]);
});

it('forbids unauthorized users from deleting a brand', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $brand = Brand::factory()->create(['name' => 'Protected']);

    $this->actingAs($user)
        ->delete(route('brands.destroy', $brand))
        ->assertForbidden();

    expect(Brand::find($brand->id))->not->toBeNull();
});

it('renders the pdf preview page with brands and headers', function () {
    actingAsSuperAdmin();

    Brand::factory()->create([
        'name' => 'Realme',
        'note' => 'Realme smartphones and wearables',
    ]);

    $this->get(route('brands.pdf-view', ['columns' => 'name,note']))
        ->assertOk()
        ->assertSee('Brands List')
        ->assertSee('Realme')
        ->assertSee('Realme smartphones and wearables')
        ->assertSee('Download / Save as PDF');
});

it('renders the print preview page with brands and grid table', function () {
    actingAsSuperAdmin();

    Brand::factory()->create(['name' => 'Realme']);

    $this->get(route('brands.print-view', ['columns' => 'name,note']))
        ->assertOk()
        ->assertSee('Brands List')
        ->assertSee('Realme')
        ->assertSee('window.print()', false);
});

it('exports brands with selected columns to excel or csv', function () {
    actingAsSuperAdmin();

    Brand::factory()->create(['name' => 'Export Brand']);

    $response = $this->get(route('brands.export', [
        'format' => 'csv',
        'columns' => 'name,note',
    ]));

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

it('searches brands by name and note', function () {
    actingAsSuperAdmin();

    Brand::factory()->create(['name' => 'Symphony', 'note' => 'Symphony smartphones (Bangladesh)']);
    Brand::factory()->create(['name' => 'Tecno', 'note' => 'Tecno smartphones']);

    $this->get(route('brands.index', ['search' => 'Symphony']))
        ->assertOk()
        ->assertSee('Symphony')
        ->assertDontSee('Tecno smartphones');
});

it('sorts brands by name', function () {
    actingAsSuperAdmin();

    Brand::factory()->create(['name' => 'Vivo']);
    Brand::factory()->create(['name' => 'Apple']);

    $response = $this->get(route('brands.index', ['sort' => 'name', 'direction' => 'asc']));
    $response->assertOk();

    expect(mb_strpos($response->getContent(), 'Apple'))->toBeLessThan(
        mb_strpos($response->getContent(), 'Vivo')
    );
});
