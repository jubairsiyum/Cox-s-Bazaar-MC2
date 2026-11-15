<?php

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('forbids guests from viewing the categories list', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});

it('forbids users without permission from viewing categories', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('categories.index'))->assertForbidden();
});

it('hides the categories action column for users without manage permission', function () {
    $salesStaff = User::factory()->create(['email_verified_at' => now()]);
    $salesStaff->assignRole('Sales Staff');

    Category::factory()->create(['name' => 'Smartphones']);

    $this->actingAs($salesStaff)->get(route('categories.index'))
        ->assertOk()
        ->assertSee('Smartphones')
        ->assertDontSee('ACTION')
        ->assertDontSee('data-col="action"', false);
});

it('shows the categories action column for users with manage permission', function () {
    actingAsSuperAdmin();

    Category::factory()->create(['name' => 'Smartphones']);

    $this->get(route('categories.index'))
        ->assertSee('ACTION')
        ->assertSee('data-col="action"', false);
});

it('lists categories for a Super Admin', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create([
        'name' => 'Smartphones',
        'category_id' => 'CT0021',
    ]);

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertSee('Categories List')
        ->assertSee('Smartphones')
        ->assertDontSee('CT0021');
});

it('shows the category create page', function () {
    actingAsSuperAdmin();

    $this->get(route('categories.create'))
        ->assertOk()
        ->assertSee('Category')
        ->assertSee('Add/Update Category')
        ->assertSee('Category Name');
});

it('creates a category and automatically assigns a CT code', function () {
    actingAsSuperAdmin();

    $response = $this->post(route('categories.store'), [
        'name' => 'Phone Cases & Covers',
        'description' => 'Back covers, flip covers and protective cases',
    ]);

    $response->assertRedirect(route('categories.index'));

    $category = Category::where('name', 'Phone Cases & Covers')->first();

    expect($category)->not->toBeNull()
        ->and($category->category_id)->toStartWith('CT')
        ->and($category->description)->toBe('Back covers, flip covers and protective cases')
        ->and($category->status)->toBe('active');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Category::class,
        'auditable_id' => $category->id,
    ]);
});

it('fails validation when category name is missing', function () {
    actingAsSuperAdmin();

    $this->post(route('categories.store'), [
        'name' => '',
        'description' => 'Some description',
    ])->assertSessionHasErrors('name');
});

it('fails validation when category name is duplicated', function () {
    actingAsSuperAdmin();

    Category::factory()->create(['name' => 'Power Banks']);

    $this->post(route('categories.store'), [
        'name' => 'Power Banks',
    ])->assertSessionHasErrors('name');
});

it('hides the category code on the category edit page', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create([
        'name' => 'Smart Watches',
        'category_id' => 'CT0015',
    ]);

    $this->get(route('categories.edit', $category))
        ->assertOk()
        ->assertSee('Add/Update Category')
        ->assertSee('Smart Watches')
        ->assertDontSee('CT0015');
});

it('updates an existing category', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create([
        'name' => 'Old Category Name',
        'description' => 'Old description',
    ]);

    $response = $this->patch(route('categories.update', $category), [
        'name' => 'Updated Category Name',
        'description' => 'New description',
    ]);

    $response->assertRedirect(route('categories.index'));

    expect($category->fresh()->name)->toBe('Updated Category Name')
        ->and($category->fresh()->description)->toBe('New description');

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => Category::class,
        'auditable_id' => $category->id,
    ]);
});

it('soft deletes a category', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create(['name' => 'To Delete']);

    $this->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    expect(Category::find($category->id))->toBeNull()
        ->and(Category::withTrashed()->find($category->id))->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'deleted',
        'auditable_type' => Category::class,
        'auditable_id' => $category->id,
    ]);
});

it('toggles category status between active and inactive', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create(['status' => 'active']);

    expect($category->isActive())->toBeTrue();

    // Toggle active -> inactive
    $this->patch(route('categories.toggle-status', $category))
        ->assertRedirect();

    expect($category->fresh()->status)->toBe('inactive')
        ->and($category->fresh()->isActive())->toBeFalse();

    // Toggle inactive -> active
    $this->patch(route('categories.toggle-status', $category))
        ->assertRedirect();

    expect($category->fresh()->status)->toBe('active')
        ->and($category->fresh()->isActive())->toBeTrue();
});

it('forbids unauthorized users from toggling category status', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $category = Category::factory()->create(['status' => 'active']);

    $this->actingAs($user)
        ->patch(route('categories.toggle-status', $category))
        ->assertForbidden();

    expect($category->fresh()->status)->toBe('active');
});

it('renders the pdf preview page with categories and headers', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create([
        'name' => 'Bluetooth Speakers',
        'category_id' => 'CT0019',
    ]);

    $this->get(route('categories.pdf-view', ['columns' => 'category_id,name,status']))
        ->assertOk()
        ->assertSee('Categories List')
        ->assertSee('Bluetooth Speakers')
        ->assertDontSee('CT0019')
        ->assertSee('Download / Save as PDF');
});

it('renders the print preview page with categories and grid table', function () {
    actingAsSuperAdmin();

    $category = Category::factory()->create([
        'name' => 'Bluetooth Speakers',
        'category_id' => 'CT0019',
    ]);

    $this->get(route('categories.print-view', ['columns' => 'category_id,name,status']))
        ->assertOk()
        ->assertSee('Categories List')
        ->assertSee('Bluetooth Speakers')
        ->assertDontSee('CT0019')
        ->assertSee('window.print()', false);
});

it('exports categories with selected columns to excel or csv', function () {
    actingAsSuperAdmin();

    Category::factory()->create(['name' => 'Export Category']);

    $response = $this->get(route('categories.export', [
        'format' => 'csv',
        'columns' => 'category_id,name,status',
    ]));

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

it('searches categories by name and code', function () {
    actingAsSuperAdmin();

    Category::factory()->create(['name' => 'Memory Cards & OTG', 'category_id' => 'CT0030']);
    Category::factory()->create(['name' => 'Chargers & Adapters', 'category_id' => 'CT0031']);

    $this->get(route('categories.index', ['search' => 'Memory']))
        ->assertOk()
        ->assertSee('Memory Cards & OTG')
        ->assertDontSee('Chargers &amp; Adapters');
});
