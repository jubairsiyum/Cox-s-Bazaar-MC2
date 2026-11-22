<?php

use App\Enums\ProductRequestStatus;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified branch manager (Outlet Manager) assigned to the given branch.
 */
function productRequestBranchManager(Location $location, string $name = 'Branch Manager'): User
{
    $user = User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

/**
 * A verified Super Admin, without acting as them.
 */
function productRequestSuperAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Super Admin User', 'email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    return $admin;
}

/**
 * The Add Product form fields, submitted as a new product request.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productRequestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Galaxy Z Fold',
        'model' => 'SM-F946',
        'barcode' => '356789012300001',
        'storage' => '256GB',
        'color' => 'Blue',
        'purchase_price' => '120000',
        'sale_price' => '150000',
        'quantity' => 4,
        'unit' => 'Box',
        'status' => 'active',
        'note' => 'Requested by the outlet',
    ], $overrides);
}

it('shows the new product request button on the products page', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);

    $this->actingAs($manager)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('New Product Request')
        ->assertSee(route('products.requests.create'))
        ->assertSee(route('products.requests.index'));
});

it('shows the request form with the same product fields as the add product form', function () {
    $location = Location::factory()->create();

    $this->actingAs(productRequestBranchManager($location))
        ->get(route('products.requests.create'))
        ->assertOk()
        ->assertSee('Product Name')
        ->assertSee('Barcode / IMEI')
        ->assertSee('Submit Request');
});

it('lets a branch manager submit a request that creates no product', function () {
    $location = Location::factory()->create(['name' => 'Outlet North']);
    $manager = productRequestBranchManager($location);

    $this->actingAs($manager)
        ->post(route('products.requests.store'), productRequestPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $productRequest = ProductRequest::query()->where('name', 'Galaxy Z Fold')->first();

    expect($productRequest)->not->toBeNull()
        ->and($productRequest->status)->toBe(ProductRequestStatus::Pending)
        ->and($productRequest->location_id)->toBe($location->id)
        ->and($productRequest->requested_by)->toBe($manager->id)
        ->and($productRequest->storage)->toBe('256GB')
        ->and($productRequest->barcode)->toBe('356789012300001')
        ->and($productRequest->quantity)->toBe(4)
        ->and($productRequest->unit)->toBe('Box')
        ->and($productRequest->product_status)->toBe('active')
        ->and((float) $productRequest->sale_price)->toBe(150000.0);

    expect(Product::query()->where('name', 'Galaxy Z Fold')->exists())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => ProductRequest::class,
        'auditable_id' => $productRequest->id,
    ]);
});

it('stores the uploaded photo with the request', function () {
    Storage::fake('public');
    $manager = productRequestBranchManager(Location::factory()->create());

    $this->actingAs($manager)
        ->post(route('products.requests.store'), productRequestPayload([
            'photo' => UploadedFile::fake()->image('fold.png'),
        ]))
        ->assertSessionHasNoErrors();

    $photo = ProductRequest::query()->where('name', 'Galaxy Z Fold')->value('photo');

    expect($photo)->toStartWith('product-photos/');
    Storage::disk('public')->assertExists($photo);
});

it('notifies the Super Admin and acknowledges the requester when a request is submitted', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $admin = productRequestSuperAdmin();

    $this->actingAs($manager)
        ->post(route('products.requests.store'), productRequestPayload())
        ->assertSessionHasNoErrors();

    $adminAlert = $admin->notifications()->first();

    expect($admin->notifications()->count())->toBe(1)
        ->and($adminAlert->data['title'])->toContain('awaits approval')
        ->and($adminAlert->data['location_id'])->toBe($location->id)
        ->and($manager->notifications()->count())->toBe(1)
        ->and($manager->notifications()->first()->data['title'])->toContain('submitted');

    $this->actingAs($admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('awaits approval');
});

it('rejects a duplicate product name and barcode when a request is submitted', function () {
    $manager = productRequestBranchManager(Location::factory()->create());

    Product::factory()->create([
        'name' => 'Galaxy Z Fold',
        'storage' => '256GB',
        'color' => 'Blue',
        'barcode' => '356789012300001',
    ]);

    $this->actingAs($manager)
        ->post(route('products.requests.store'), productRequestPayload())
        ->assertSessionHasErrors('name');

    $this->actingAs($manager)
        ->post(route('products.requests.store'), productRequestPayload([
            'name' => 'Galaxy Z Flip',
            'storage' => '512GB',
        ]))
        ->assertSessionHasErrors('barcode');

    expect(ProductRequest::query()->count())->toBe(0);
});

it('forbids a user without a branch from submitting a request', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Outlet Manager');

    $this->actingAs($user)
        ->post(route('products.requests.store'), productRequestPayload())
        ->assertForbidden();

    expect(ProductRequest::query()->count())->toBe(0);
});

it('allows only the Super Admin to approve or reject a request', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $productRequest = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Galaxy Z Fold',
        'barcode' => '356789012300001',
    ]);

    $this->actingAs($manager)
        ->patch(route('products.requests.approve', $productRequest))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('products.requests.reject', $productRequest), ['reason' => 'Not needed'])
        ->assertForbidden();

    expect($productRequest->fresh()->status)->toBe(ProductRequestStatus::Pending)
        ->and(Product::query()->where('name', 'Galaxy Z Fold')->exists())->toBeFalse();
});

it('creates the product with the submitted details when the Super Admin approves', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $otherBranchManager = productRequestBranchManager(Location::factory()->create(), 'Other Branch Manager');
    $admin = productRequestSuperAdmin();

    $productRequest = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Galaxy Z Fold',
        'model' => 'SM-F946',
        'barcode' => '356789012300001',
        'storage' => '256GB',
        'color' => 'Blue',
        'purchase_price' => 120000,
        'sale_price' => 150000,
        'quantity' => 4,
        'unit' => 'Box',
        'product_status' => 'active',
        'note' => 'Requested by the outlet',
    ]);

    $this->actingAs($admin)
        ->patch(route('products.requests.approve', $productRequest))
        ->assertRedirect(route('products.requests.show', $productRequest));

    $product = Product::query()->where('name', 'Galaxy Z Fold')->first();

    expect($product)->not->toBeNull()
        ->and($product->product_id)->toStartWith('PRD')
        ->and($product->storage)->toBe('256GB')
        ->and($product->color)->toBe('Blue')
        ->and($product->barcode)->toBe('356789012300001')
        ->and((float) $product->sale_price)->toBe(150000.0)
        ->and($product->stockRow->quantity)->toBe(4)
        ->and($product->stockRow->unit)->toBe('Box');

    $productRequest->refresh();

    expect($productRequest->status)->toBe(ProductRequestStatus::Approved)
        ->and($productRequest->created_product_id)->toBe($product->id)
        ->and($productRequest->reviewed_by)->toBe($admin->id)
        ->and($productRequest->reviewed_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'created',
        'auditable_type' => Product::class,
        'auditable_id' => $product->id,
    ]);

    $requesterAlert = $manager->notifications()->first();

    expect($requesterAlert->data['title'])->toContain('approved')
        ->and($requesterAlert->data['type'])->toBe('success')
        ->and($otherBranchManager->notifications()->count())->toBe(0);
});

it('rejects a request only with a reason and creates nothing', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $admin = productRequestSuperAdmin();
    $productRequest = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Galaxy Z Fold',
    ]);

    $this->actingAs($admin)
        ->patch(route('products.requests.reject', $productRequest), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($productRequest->fresh()->status)->toBe(ProductRequestStatus::Pending);

    $this->actingAs($admin)
        ->patch(route('products.requests.reject', $productRequest), ['reason' => 'Duplicate of an existing model'])
        ->assertRedirect(route('products.requests.show', $productRequest));

    $productRequest->refresh();

    expect($productRequest->status)->toBe(ProductRequestStatus::Rejected)
        ->and($productRequest->rejection_reason)->toBe('Duplicate of an existing model')
        ->and($productRequest->reviewed_by)->toBe($admin->id)
        ->and(Product::query()->where('name', 'Galaxy Z Fold')->exists())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'event' => 'updated',
        'auditable_type' => ProductRequest::class,
        'auditable_id' => $productRequest->id,
    ]);

    $requesterAlert = $manager->notifications()->first();

    expect($requesterAlert->data['title'])->toContain('rejected')
        ->and($requesterAlert->data['type'])->toBe('error')
        ->and($requesterAlert->data['body'])->toContain('Duplicate of an existing model');
});

it('blocks approval when the product became a duplicate after the request was submitted', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $admin = productRequestSuperAdmin();

    $productRequest = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Galaxy Z Fold',
        'storage' => '256GB',
        'color' => 'Blue',
        'barcode' => '356789012300001',
    ]);

    Product::factory()->create([
        'name' => 'Galaxy Z Fold',
        'storage' => '256GB',
        'color' => 'Blue',
        'barcode' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('products.requests.approve', $productRequest))
        ->assertSessionHas('error');

    expect($productRequest->fresh()->status)->toBe(ProductRequestStatus::Pending)
        ->and(Product::query()->where('name', 'Galaxy Z Fold')->count())->toBe(1);
});

it('blocks approval when the barcode was taken after the request was submitted', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $admin = productRequestSuperAdmin();

    $productRequest = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Galaxy Z Fold',
        'barcode' => '356789012300001',
    ]);

    Product::factory()->create(['name' => 'Other Phone', 'barcode' => '356789012300001']);

    $this->actingAs($admin)
        ->patch(route('products.requests.approve', $productRequest))
        ->assertSessionHas('error');

    expect($productRequest->fresh()->status)->toBe(ProductRequestStatus::Pending)
        ->and(Product::query()->where('name', 'Galaxy Z Fold')->exists())->toBeFalse();
});

it('shows approve and reject actions only to the Super Admin on pending requests', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);
    $admin = productRequestSuperAdmin();

    $productRequest = ProductRequest::factory()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Galaxy Z Fold',
    ]);

    $this->actingAs($manager)
        ->get(route('products.requests.index'))
        ->assertOk()
        ->assertSee('Galaxy Z Fold')
        ->assertDontSee(route('products.requests.approve', $productRequest));

    $this->actingAs($admin)
        ->get(route('products.requests.index'))
        ->assertOk()
        ->assertSee(route('products.requests.approve', $productRequest));
});

it('keeps a branch request private to its own branch and the Super Admin', function () {
    $branchA = Location::factory()->create(['name' => 'Branch A Outlet']);
    $branchB = Location::factory()->create(['name' => 'Branch B Outlet']);
    $managerA = productRequestBranchManager($branchA, 'Manager A');
    $managerB = productRequestBranchManager($branchB, 'Manager B');
    $admin = productRequestSuperAdmin();

    $productRequest = ProductRequest::factory()->create([
        'location_id' => $branchA->id,
        'requested_by' => $managerA->id,
        'name' => 'Secret Phone',
    ]);

    $this->actingAs($managerB)
        ->get(route('products.requests.show', $productRequest))
        ->assertForbidden();

    $this->actingAs($managerB)
        ->get(route('products.requests.index'))
        ->assertOk()
        ->assertDontSee('Secret Phone');

    $this->actingAs($managerA)
        ->get(route('products.requests.show', $productRequest))
        ->assertOk()
        ->assertSee('Secret Phone');

    $this->actingAs($admin)
        ->get(route('products.requests.show', $productRequest))
        ->assertOk()
        ->assertSee('Secret Phone');
});

it('lets a requester see the status of their own request', function () {
    $location = Location::factory()->create();
    $manager = productRequestBranchManager($location);

    ProductRequest::factory()->rejected()->create([
        'location_id' => $location->id,
        'requested_by' => $manager->id,
        'name' => 'Pixel Tablet',
    ]);

    $this->actingAs($manager)
        ->get(route('products.requests.index', ['status' => 'rejected']))
        ->assertOk()
        ->assertSee('Pixel Tablet')
        ->assertSee('Rejected');
});
