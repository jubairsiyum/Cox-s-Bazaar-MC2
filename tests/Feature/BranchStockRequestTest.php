<?php

declare(strict_types=1);

use App\Enums\StockTransferStatus;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

/**
 * A verified Outlet Manager assigned to the given location.
 */
function requestManager(Location $location): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $user->assignRole('Outlet Manager');

    return $user;
}

/**
 * Put stock of a unit on the ledger at one location.
 */
function restockAtLocation(Unit $unit, Location $location, int $quantity): void
{
    StockService::restock($unit, $quantity, null, 'Opening stock', $location->id);
}

/**
 * A request from the branch to the source, submitted through the product flow.
 *
 * @return array<string, mixed>
 */
function requestPayload(Location $source, Unit $unit, int $quantity = 20): array
{
    return [
        'from_location_id' => $source->id,
        'additional_notes' => 'Needed for weekend sale',
        'items' => [['unit_id' => $unit->id, 'quantity' => $quantity]],
    ];
}

/**
 * Titles of a user's in-app notifications.
 *
 * @return array<int, string>
 */
function notificationTitles(User $user): array
{
    return $user->fresh()->notifications->pluck('data.title')->all();
}

it('offers Request Stock on the products list to a branch manager', function () {
    $branch = Location::factory()->create();
    $product = Product::factory()->create(['name' => 'Galaxy Request Test']);
    $unit = Unit::factory()->for($product)->create(['quantity' => 0]);
    restockAtLocation($unit, $branch, 5);

    $this->actingAs(requestManager($branch))
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('Request Stock')
        ->assertSee(route('stock-transfers.requests.create', ['product' => $product->id]), false);
});

it('lets a branch manager request stock from the warehouse without a quantity cap', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    $manager = requestManager($branch);

    $this->actingAs($manager)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 500))
        ->assertRedirect();

    $transfer = StockTransfer::query()->sole();

    expect($transfer->is_request)->toBeTrue()
        ->and($transfer->status)->toBe(StockTransferStatus::Requested)
        ->and($transfer->from_location_id)->toBe($warehouse->id)
        ->and($transfer->to_location_id)->toBe($branch->id)
        ->and($transfer->created_by)->toBe($manager->id)
        ->and($transfer->additional_notes)->toBe('Needed for weekend sale')
        ->and((int) $transfer->items()->sum('quantity'))->toBe(500);
});

it('does not move stock until the source approves the request', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $this->actingAs(requestManager($branch))
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20))
        ->assertRedirect();

    expect(StockService::quantityAt($unit, $warehouse->id))->toBe(50)
        ->and(StockService::quantityAt($unit, $branch->id))->toBe(0)
        ->and($unit->fresh()->quantity)->toBe(50);
});

it('alerts the warehouse managers and acknowledges the requester when a request is sent', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $warehouseManager = requestManager($warehouse);
    $requester = requestManager($branch);

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20))
        ->assertRedirect();

    $approvalAlert = $warehouseManager->fresh()->notifications->firstWhere('data.type', 'warning');

    expect($approvalAlert)->not->toBeNull()
        ->and($approvalAlert->data['title'])->toContain('awaits your approval')
        ->and($approvalAlert->data['location_id'])->toBe($warehouse->id)
        ->and(collect(notificationTitles($requester))->contains(fn (string $title): bool => str_contains($title, 'submitted')))->toBeTrue();
});

it('accepts form string ids and still notifies the source managers', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $warehouseManager = requestManager($warehouse);
    $requester = requestManager($branch);

    // The browser submits url-encoded strings, not typed integers.
    $payload = requestPayload($warehouse, $unit, 20);
    $payload['from_location_id'] = (string) $payload['from_location_id'];
    $payload['items'][0]['unit_id'] = (string) $payload['items'][0]['unit_id'];
    $payload['items'][0]['quantity'] = (string) $payload['items'][0]['quantity'];

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), $payload)
        ->assertRedirect();

    $transfer = StockTransfer::query()->sole();

    expect($transfer->from_location_id)->toBe($warehouse->id)
        ->and($transfer->to_location_id)->toBe($branch->id);

    $approvalAlert = $warehouseManager->fresh()->notifications->firstWhere('data.type', 'warning');

    expect($approvalAlert)->not->toBeNull()
        ->and($approvalAlert->data['location_id'])->toBe($warehouse->id);
});

it('also alerts the Super Admin of a new request', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $superAdmin = User::factory()->create(['email_verified_at' => now()]);
    $superAdmin->assignRole('Super Admin');

    $this->actingAs(requestManager($branch))
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20))
        ->assertRedirect();

    expect(collect(notificationTitles($superAdmin))->contains(fn (string $title): bool => str_contains($title, 'awaits your approval')))->toBeTrue();
});

it('moves stock and notifies the requesting branch when the warehouse approves', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $warehouseManager = requestManager($warehouse);
    $requester = requestManager($branch);

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20))
        ->assertRedirect();

    $transfer = StockTransfer::query()->sole();

    $this->actingAs($warehouseManager)
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertRedirect(route('stock-transfers.index'));

    $transfer->refresh();

    expect($transfer->status)->toBe(StockTransferStatus::Received)
        ->and($transfer->approved_by)->toBe($warehouseManager->id)
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(30)
        ->and(StockService::quantityAt($unit, $branch->id))->toBe(20)
        ->and($unit->fresh()->quantity)->toBe(50);

    $received = $requester->fresh()->notifications->firstWhere('data.type', 'success');

    expect($received)->not->toBeNull()
        ->and($received->data['title'])->toContain('approved and received')
        ->and($received->data['location_id'])->toBe($branch->id)
        ->and($requester->fresh()->notifications->firstWhere('data.type', 'success')->data['title'])->toContain($transfer->reference_no);

    $this->actingAs($requester)
        ->get(route('stock-transfers.show', $transfer))
        ->assertOk()
        ->assertSee('Received');
});

it('moves no stock and tells the requester why when the warehouse rejects', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $warehouseManager = requestManager($warehouse);
    $requester = requestManager($branch);

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20))
        ->assertRedirect();

    $transfer = StockTransfer::query()->sole();

    $this->actingAs($warehouseManager)
        ->patch(route('stock-transfers.reject', $transfer), ['reason' => 'Warehouse is reserved for the wholesale order'])
        ->assertRedirect(route('stock-transfers.index'));

    $transfer->refresh();

    expect($transfer->status)->toBe(StockTransferStatus::Rejected)
        ->and($transfer->rejection_reason)->toBe('Warehouse is reserved for the wholesale order')
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(50)
        ->and(StockService::quantityAt($unit, $branch->id))->toBe(0);

    $rejected = $requester->fresh()->notifications->firstWhere('data.type', 'error');

    expect($rejected)->not->toBeNull()
        ->and($rejected->data['title'])->toContain('rejected')
        ->and($rejected->data['body'])->toContain('Warehouse is reserved for the wholesale order');
});

it('requires a reason to reject a stock request', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $this->actingAs(requestManager($branch))
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20));

    $transfer = StockTransfer::query()->sole();

    $this->actingAs(requestManager($warehouse))
        ->patch(route('stock-transfers.reject', $transfer))
        ->assertSessionHasErrors('reason');

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Requested);
});

it('stops the requesting branch manager from approving or rejecting their own request', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $requester = requestManager($branch);

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20));

    $transfer = StockTransfer::query()->sole();

    $this->actingAs($requester)
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertForbidden();

    $this->actingAs($requester)
        ->patch(route('stock-transfers.reject', $transfer), ['reason' => 'Changed my mind'])
        ->assertForbidden();

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Requested)
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(50);
});

it('offers only other locations as the request source', function () {
    $branch = Location::factory()->create(['name' => 'Own Outlet Test']);
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $otherShop = Location::factory()->brandShop()->create(['name' => 'Partner Shop Test']);

    $this->actingAs(requestManager($branch))
        ->get(route('stock-transfers.requests.create', ['product' => Unit::factory()->create()->product_id]))
        ->assertOk()
        ->assertSee($warehouse->name)
        ->assertSee($otherShop->name)
        ->assertDontSee('Own Outlet Test</option>', false);
});

it('stops a branch user from requesting stock from their own branch', function () {
    $branch = Location::factory()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);

    $this->actingAs(requestManager($branch))
        ->post(route('stock-transfers.requests.store'), requestPayload($branch, $unit, 5))
        ->assertSessionHasErrors('from_location_id');

    $this->assertDatabaseCount('stock_transfers', 0);
});

it('does not give a user without a branch the request form', function () {
    $unit = Unit::factory()->create(['quantity' => 0]);
    $warehouse = Location::factory()->warehouse()->create();

    $this->actingAs(actingAsSuperAdmin())
        ->get(route('stock-transfers.requests.create'))
        ->assertForbidden();

    $this->actingAs(actingAsSuperAdmin())
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 5))
        ->assertForbidden();

    $this->assertDatabaseCount('stock_transfers', 0);
});

it('hides a request from managers of other branches and blocks them from deciding it', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $this->actingAs(requestManager($branch))
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20));

    $transfer = StockTransfer::query()->sole();
    $outsider = requestManager(Location::factory()->create());

    $this->flushSession();

    $this->actingAs($outsider)
        ->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertDontSee($transfer->reference_no);

    $this->actingAs($outsider)
        ->get(route('stock-transfers.show', $transfer))
        ->assertForbidden();

    $this->actingAs($outsider)
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertForbidden();

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Requested);
});

it('shows the request to the source as awaiting approval and to the requester as requested', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $requester = requestManager($branch);
    $source = requestManager($warehouse);

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20));

    $transfer = StockTransfer::query()->sole();

    expect($transfer->displayStatusFor($requester)['label'])->toBe('Requested')
        ->and($transfer->displayStatusFor($source)['label'])->toBe('Awaiting Approval');

    $this->actingAs($source)
        ->get(route('stock-transfers.index'))
        ->assertOk()
        ->assertSee($transfer->reference_no)
        ->assertSee('Requested from '.$warehouse->name)
        ->assertSee('Awaiting Approval');
});

it('does not approve a request the source cannot cover and leaves stock untouched', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 5);

    $this->actingAs(requestManager($branch))
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20));

    $transfer = StockTransfer::query()->sole();

    $this->actingAs(requestManager($warehouse))
        ->patch(route('stock-transfers.approve', $transfer))
        ->assertSessionHas('error');

    expect($transfer->fresh()->status)->toBe(StockTransferStatus::Requested)
        ->and(StockService::quantityAt($unit, $warehouse->id))->toBe(5)
        ->and(StockService::quantityAt($unit, $branch->id))->toBe(0);
});

it('keeps a request notification in the requesting branch notification centre only', function () {
    $branch = Location::factory()->create();
    $warehouse = Location::factory()->warehouse()->create();
    $unit = Unit::factory()->create(['quantity' => 0]);
    restockAtLocation($unit, $warehouse, 50);

    $requester = requestManager($branch);
    $warehouseManager = requestManager($warehouse);

    $this->actingAs($requester)
        ->post(route('stock-transfers.requests.store'), requestPayload($warehouse, $unit, 20));

    $transfer = StockTransfer::query()->sole();

    $this->actingAs($warehouseManager)
        ->patch(route('stock-transfers.reject', $transfer), ['reason' => 'Out of stock this week'])
        ->assertRedirect();

    $this->actingAs($requester)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee($transfer->reference_no);

    $otherBranchManager = requestManager(Location::factory()->create());

    $this->actingAs($otherBranchManager)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee($transfer->reference_no);
});
