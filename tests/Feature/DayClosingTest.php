<?php

declare(strict_types=1);

use App\Enums\SalesInvoiceStatus;
use App\Exceptions\DayClosedException;
use App\Models\DayClosing;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Services\DayClosingService;
use App\Services\SalesInvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('allows a branch manager to close the business day with counted cash and records variance', function () {
    $branch = Location::factory()->create(['name' => 'Sadar Outlet']);

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->givePermissionTo(['finance.view', 'finance.manage', 'dashboard.view']);

    // Today's date
    $today = now()->toDateString();

    // 1. Manager visits the day close form
    $this->actingAs($manager)
        ->get(route('day-closings.create'))
        ->assertOk()
        ->assertSee('Sadar Outlet')
        ->assertSee('Expected System Cash');

    // 2. Manager submits counted cash: expected is 0, counted is 5000 -> variance +5000
    $this->actingAs($manager)
        ->post(route('day-closings.store'), [
            'location_id' => $branch->id,
            'closing_date' => $today,
            'counted_cash' => '5000.00',
            'note' => 'Night shift closing',
        ])
        ->assertRedirect(route('day-closings.index'));

    $closing = DayClosing::query()->where('location_id', $branch->id)->whereDate('closing_date', $today)->firstOrFail();

    expect($closing->status)->toBe('closed')
        ->and((float) $closing->counted_cash)->toBe(5000.0)
        ->and((float) $closing->system_cash)->toBe(0.0)
        ->and((float) $closing->variance)->toBe(5000.0)
        ->and($closing->variance_status)->toBe('significant')
        ->and($closing->closed_by)->toBe($manager->id);
});

it('locks the day and blocks creating POS sales or posting invoices on closed dates', function () {
    $branch = Location::factory()->create(['name' => 'Kolatoli Branch']);

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->givePermissionTo(['finance.manage', 'dashboard.view', 'sales-invoices.manage', 'pos.access']);

    $today = now()->toDateString();

    // Close the day
    DayClosingService::closeDay($branch->id, $today, '1000', $manager);
    expect(DayClosingService::isDayClosed($branch->id, $today))->toBeTrue();

    // Attempting a POS sale today is blocked by day closing
    $unit = Unit::factory()->create(['quantity' => 10]);
    stockAtLocation($unit, 10, $branch->id);

    $this->actingAs($manager)
        ->post(route('pos.store'), [
            'location_id' => $branch->id,
            'items' => [
                ['unit_id' => $unit->id, 'quantity' => 1, 'unit_price' => 500],
            ],
            'paid_amount' => 500,
        ])
        ->assertSessionHas('error');

    // Attempting to post a sales invoice for today throws DayClosedException
    $invoice = SalesInvoice::factory()->create([
        'location_id' => $branch->id,
        'invoice_date' => $today,
        'status' => SalesInvoiceStatus::Draft,
    ]);

    expect(fn () => SalesInvoiceService::post($invoice))
        ->toThrow(DayClosedException::class);
});

it('blocks a branch manager from reopening a closed day and allows Super Admin to reopen with reason', function () {
    $branch = Location::factory()->create();

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->givePermissionTo(['finance.manage', 'dashboard.view']);

    $today = now()->toDateString();
    $closing = DayClosingService::closeDay($branch->id, $today, '2000', $manager);

    // Branch manager cannot reopen (403 Forbidden)
    $this->actingAs($manager)
        ->patch(route('day-closings.reopen', $closing), [
            'reopen_reason' => 'Need to add one more sale',
        ])
        ->assertForbidden();

    // Super Admin can reopen
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->patch(route('day-closings.reopen', $closing), [
            'reopen_reason' => 'Audited: cash drawer error rectified',
        ])
        ->assertRedirect(route('day-closings.index'));

    $closing->refresh();

    expect($closing->status)->toBe('reopened')
        ->and($closing->reopened_by)->toBe($admin->id)
        ->and($closing->reopen_reason)->toBe('Audited: cash drawer error rectified')
        ->and(DayClosingService::isDayClosed($branch->id, $today))->toBeFalse();
});

it('blocks a manager from closing another branch location', function () {
    $branchA = Location::factory()->create();
    $branchB = Location::factory()->create();

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branchA->id,
    ]);
    $manager->givePermissionTo(['finance.manage', 'dashboard.view']);

    $this->actingAs($manager)
        ->post(route('day-closings.store'), [
            'location_id' => $branchB->id,
            'closing_date' => now()->toDateString(),
            'counted_cash' => '1000',
        ])
        ->assertSessionHas('error');

    expect(DayClosing::query()->count())->toBe(0);
});

it('shows a locked status button on index and blocks re-closing an already closed day', function () {
    $branch = Location::factory()->create();

    $manager = User::factory()->create([
        'status' => 'active',
        'branch_id' => $branch->id,
    ]);
    $manager->givePermissionTo(['finance.view', 'finance.manage', 'dashboard.view']);

    $today = now()->toDateString();

    // 1. Initially, today is not closed, so "Close Today's Cash" is shown
    $this->actingAs($manager)
        ->get(route('day-closings.index'))
        ->assertOk()
        ->assertSeeText("Close Today's Cash")
        ->assertDontSeeText("Today's Cash Locked");

    // 2. Close today's cash
    DayClosingService::closeDay($branch->id, $today, '3000', $manager);

    // 3. Now index shows locked status button and does NOT show active close button
    $this->actingAs($manager)
        ->get(route('day-closings.index'))
        ->assertOk()
        ->assertSeeText("Today's Cash Locked")
        ->assertDontSeeText("Close Today's Cash");

    // 4. Visiting create form for today redirects back with error
    $this->actingAs($manager)
        ->get(route('day-closings.create', ['closing_date' => $today]))
        ->assertRedirect(route('day-closings.index'))
        ->assertSessionHas('error');

    // 5. Posting again for today is rejected
    $this->actingAs($manager)
        ->post(route('day-closings.store'), [
            'location_id' => $branch->id,
            'closing_date' => $today,
            'counted_cash' => '3500',
        ])
        ->assertRedirect(route('day-closings.index'))
        ->assertSessionHas('error');
});

it('tracks today branch closing compliance and shows unclosed branches on day closings index', function () {
    $branchA = Location::factory()->brandShop()->create(['name' => 'Cox Sadar Branch']);
    $branchB = Location::factory()->brandShop()->create(['name' => 'Teknaf Outlet']);
    $warehouse = Location::factory()->warehouse()->create(['name' => 'Main Warehouse']);

    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    $managerA = User::factory()->create(['branch_id' => $branchA->id, 'status' => 'active']);

    $today = now()->toDateString();

    // 1. Initially neither branch is closed
    $compliance = DayClosingService::getTodayBranchCompliance($today);
    expect($compliance['total_branches'])->toBe(2) // Only retail branches, warehouse excluded
        ->and($compliance['closed_count'])->toBe(0)
        ->and($compliance['unclosed_count'])->toBe(2)
        ->and($compliance['compliance_pct'])->toBe(0);

    // 2. Branch A closes today
    DayClosingService::closeDay($branchA->id, $today, '25000', $managerA);

    $compliance = DayClosingService::getTodayBranchCompliance($today);
    expect($compliance['closed_count'])->toBe(1)
        ->and($compliance['unclosed_count'])->toBe(1)
        ->and($compliance['compliance_pct'])->toBe(50);

    // 3. Super Admin visits day-closings index and sees the compliance tracker
    $this->actingAs($admin)
        ->get(route('day-closings.index'))
        ->assertOk()
        ->assertSeeText("Today's Branch Closing Status")
        ->assertSee('Cox Sadar Branch')
        ->assertSee('Teknaf Outlet')
        ->assertSee('Pending Close')
        ->assertSee('1 of 2 Branches Closed');
});

it('blocks reopening past days and hides reopen button on index for historical days', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Sadar Branch']);

    $manager = User::factory()->create(['branch_id' => $branch->id, 'status' => 'active']);
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    // Close yesterday (past day)
    $yesterday = now()->subDay()->toDateString();
    $closing = DayClosingService::closeDay($branch->id, $yesterday, '5000', $manager);

    // Super Admin cannot reopen a past day (403 Forbidden)
    $this->actingAs($admin)
        ->patch(route('day-closings.reopen', $closing), [
            'reopen_reason' => 'Attempting to reopen yesterday',
        ])
        ->assertForbidden();

    // On index, past day shows "Finalized" badge and does NOT show an active Reopen form
    $this->actingAs($admin)
        ->get(route('day-closings.index'))
        ->assertOk()
        ->assertSee('Finalized')
        ->assertSee('Details')
        ->assertDontSee('Reopen Shift');
});

it('displays cash sales and remittances on day closings index table instead of only 0', function () {
    $branch = Location::factory()->brandShop()->create(['name' => 'CTG Vivo']);

    $manager = User::factory()->create(['branch_id' => $branch->id, 'status' => 'active']);
    $admin = User::factory()->create(['status' => 'active']);
    $admin->assignRole('Super Admin');

    // Create closing with 1,770,000 in sales and remittances
    $today = now()->toDateString();
    $closing = DayClosing::create([
        'location_id' => $branch->id,
        'closing_date' => $today,
        'cash_sales' => '1770000.00',
        'cash_remittances' => '1770000.00',
        'system_cash' => '0.00',
        'counted_cash' => '0.00',
        'variance' => '0.00',
        'variance_status' => 'exact',
        'status' => 'closed',
        'closed_by' => $manager->id,
    ]);

    $this->actingAs($admin)
        ->get(route('day-closings.index'))
        ->assertOk()
        ->assertSee('1,770,000.00')
        ->assertSee('Details')
        ->assertSee('Reopen');
});
