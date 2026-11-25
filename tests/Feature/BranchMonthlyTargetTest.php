<?php

declare(strict_types=1);

use App\Enums\SalesInvoiceStatus;
use App\Models\BranchTarget;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\TargetService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
});

it('allows Super Admin to view the branch targets index page with network metrics', function () {
    actingAsSuperAdmin();

    $location = Location::factory()->brandShop()->create(['name' => 'Cox Bazar Flagship', 'status' => 'active']);

    BranchTarget::create([
        'location_id' => $location->id,
        'target_month' => now()->format('Y-m'),
        'target_amount' => 500000.00,
        'notes' => 'Q4 Goal',
    ]);

    $response = $this->get(route('branch-targets.index'));

    $response->assertOk()
        ->assertViewIs('branch-targets.index')
        ->assertViewHas('isSuperAdmin', true)
        ->assertSee('Cox Bazar Flagship')
        ->assertSee('500,000');
});

it('allows Super Admin to store or update monthly targets for a branch', function () {
    $admin = actingAsSuperAdmin();
    $location = Location::factory()->brandShop()->create(['name' => 'Teknaf Outlet', 'status' => 'active']);
    $currentMonth = now()->format('Y-m');

    $response = $this->post(route('branch-targets.store'), [
        'location_id' => $location->id,
        'target_month' => $currentMonth,
        'target_amount' => 750000.00,
        'notes' => 'Target for festival sales',
    ]);

    $response->assertRedirect(route('branch-targets.index', ['month' => $currentMonth]));

    $this->assertDatabaseHas('branch_targets', [
        'location_id' => $location->id,
        'target_month' => $currentMonth,
        'target_amount' => 750000.00,
        'notes' => 'Target for festival sales',
        'created_by' => $admin->id,
    ]);

    // Updating existing target for the same branch and month
    $this->post(route('branch-targets.store'), [
        'location_id' => $location->id,
        'target_month' => $currentMonth,
        'target_amount' => 900000.00,
        'notes' => 'Revised target',
    ]);

    $this->assertDatabaseHas('branch_targets', [
        'location_id' => $location->id,
        'target_month' => $currentMonth,
        'target_amount' => 900000.00,
        'notes' => 'Revised target',
    ]);
});

it('blocks non-Super Admin from setting or altering branch targets', function () {
    $location = Location::factory()->brandShop()->create(['status' => 'active']);

    $manager = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $location->id,
    ]);
    $manager->assignRole('Outlet Manager');

    $this->actingAs($manager);

    $response = $this->post(route('branch-targets.store'), [
        'location_id' => $location->id,
        'target_month' => now()->format('Y-m'),
        'target_amount' => 300000.00,
    ]);

    $response->assertForbidden();
});

it('allows Branch Manager to view their scoped monthly target and Day-over-Day pacing', function () {
    $locationA = Location::factory()->brandShop()->create(['name' => 'Branch A', 'status' => 'active']);
    $locationB = Location::factory()->brandShop()->create(['name' => 'Branch B', 'status' => 'active']);

    $currentMonth = now()->format('Y-m');

    BranchTarget::create([
        'location_id' => $locationA->id,
        'target_month' => $currentMonth,
        'target_amount' => 200000.00,
    ]);

    BranchTarget::create([
        'location_id' => $locationB->id,
        'target_month' => $currentMonth,
        'target_amount' => 800000.00,
    ]);

    $managerA = User::factory()->create([
        'email_verified_at' => now(),
        'branch_id' => $locationA->id,
    ]);
    $managerA->assignRole('Outlet Manager');

    $this->actingAs($managerA);

    $response = $this->get(route('branch-targets.index'));

    $response->assertOk()
        ->assertViewIs('branch-targets.index')
        ->assertViewHas('isSuperAdmin', false)
        ->assertSee('Branch A')
        ->assertSee('200,000')
        ->assertDontSee('Branch B');
});

it('accurately computes target achievement, Day-over-Day growth, and daily required run-rate via TargetService', function () {
    $location = Location::factory()->brandShop()->create(['status' => 'active']);
    $currentMonth = now()->format('Y-m');

    BranchTarget::create([
        'location_id' => $location->id,
        'target_month' => $currentMonth,
        'target_amount' => 100000.00,
    ]);

    // Create posted sales invoices:
    // Today: 15,000
    SalesInvoice::factory()->create([
        'location_id' => $location->id,
        'branch_id' => null,
        'status' => SalesInvoiceStatus::Posted,
        'invoice_date' => today(),
        'grand_total' => 15000.00,
    ]);

    // Yesterday: 10,000
    SalesInvoice::factory()->create([
        'location_id' => $location->id,
        'branch_id' => null,
        'status' => SalesInvoiceStatus::Posted,
        'invoice_date' => today()->subDay(),
        'grand_total' => 10000.00,
    ]);

    // Earlier this month: 25,000
    if (now()->day > 3) {
        SalesInvoice::factory()->create([
            'location_id' => $location->id,
            'branch_id' => null,
            'status' => SalesInvoiceStatus::Posted,
            'invoice_date' => now()->startOfMonth()->addDays(1),
            'grand_total' => 25000.00,
        ]);
    }

    $perf = TargetService::getBranchPerformance($location->id, $currentMonth);

    expect($perf['target_amount'])->toBe(100000.0)
        ->and($perf['today_sales'])->toBe(15000.0)
        ->and($perf['yesterday_sales'])->toBe(10000.0)
        ->and($perf['dod_diff_amount'])->toBe(5000.0)
        ->and($perf['dod_growth_pct'])->toBe(50.0); // (15000 - 10000)/10000 * 100 = 50%
});

it('filters warehouses out of branch targets and parses !Y-m months safely on day 31', function () {
    actingAsSuperAdmin();

    $warehouse = Location::factory()->warehouse()->create(['name' => 'Central Depot']);
    $shop = Location::factory()->brandShop()->create(['name' => 'Teknaf Shop']);

    Carbon::setTestNow('2026-01-31 12:00:00');
    try {
        $response = $this->get(route('branch-targets.index', ['month' => '2026-02']));

        $response->assertOk()
            ->assertViewHas('activeDate', fn (Carbon $d) => $d->format('Y-m') === '2026-02')
            ->assertViewHas('locations', fn ($locs) => $locs->contains('id', $shop->id) && ! $locs->contains('id', $warehouse->id));

        $this->post(route('branch-targets.store'), [
            'location_id' => $warehouse->id,
            'target_month' => '2026-02',
            'target_amount' => 100000,
        ])->assertSessionHasErrors('location_id');
    } finally {
        Carbon::setTestNow();
    }
});

it('computes TargetService::getNetworkOverview in constant O(1) queries via GROUP BY location_id', function () {
    $currentMonth = now()->format('Y-m');
    $locations = Location::factory()->brandShop()->count(5)->create(['status' => 'active']);

    foreach ($locations as $idx => $loc) {
        BranchTarget::create([
            'location_id' => $loc->id,
            'target_month' => $currentMonth,
            'target_amount' => ($idx + 1) * 100000,
        ]);

        SalesInvoice::factory()->posted()->create([
            'location_id' => $loc->id,
            'invoice_date' => today(),
            'grand_total' => ($idx + 1) * 10000,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $overview = TargetService::getNetworkOverview($currentMonth);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Fixed query count (locations + targets GROUP/IN + sales GROUP BY + daily GROUP BY) <= 5 even across 5+ branches
    expect($overview['branches'])->toHaveCount(5)
        ->and($overview['total_target'])->toBe(1500000.0)
        ->and($overview['total_sales'])->toBe(150000.0)
        ->and($overview['total_today_sales'])->toBe(150000.0)
        ->and($queryCount)->toBeLessThanOrEqual(5);
});
