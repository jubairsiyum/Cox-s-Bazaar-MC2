<?php

use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders the dashboard for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Stock Value')
        ->assertSee('Sales & Collection Trend')
        ->assertSee('Low Stock & Reorder Alerts')
        ->assertSee('Recent Distribution Invoices');
});

it('renders tailadmin showcase routes for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('ecommerce'))->assertOk();
    $this->actingAs($user)->get(route('calendar'))->assertOk();
    $this->actingAs($user)->get(route('alerts'))->assertOk();
    $this->actingAs($user)->get(route('buttons'))->assertOk();
});

it('attaches security headers to authenticated ERP responses', function () {
    actingAsSuperAdmin();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('caches dashboard metrics via DashboardMetricsService and invalidates when invoices change', function () {
    $this->seed(SettingsSeeder::class);
    actingAsSuperAdmin();

    $branch = Location::factory()->brandShop()->create(['name' => 'Cox Flagship']);

    // Warm dashboard cache on first request
    $this->get(route('dashboard'))->assertOk();

    // Second request hits cache without re-running dashboard aggregate SQL
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $queriesOnCacheHit = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_contains($sql, 'sales_invoices') || str_contains($sql, 'stock_movements'))
        ->count();
    DB::disableQueryLog();

    expect($queriesOnCacheHit)->toBe(0);

    // Mutating a SalesInvoice flushes dashboard cache version immediately
    SalesInvoice::factory()->posted()->create([
        'location_id' => $branch->id,
        'grand_total' => 42000,
        'paid_amount' => 42000,
        'invoice_date' => now()->toDateString(),
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('42,000');

    // Dashboard Blade template must not embed raw SQL queries or whereDate calls
    $blade = file_get_contents(resource_path('views/dashboard/index.blade.php'));
    expect($blade)->not->toContain('whereDate(')
        ->not->toContain('SalesInvoice::query');
});

it('serializes dashboard cache payloads with only primitives under Laravel serializable_classes => false', function () {
    $this->seed(SettingsSeeder::class);
    config()->set('cache.default', 'database');
    Cache::forgetDriver('database');

    $admin = actingAsSuperAdmin();
    Location::factory()->brandShop()->create(['name' => 'Serialized Branch']);

    // First request populates the database cache store; second unserializes with allowed_classes => false
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Serialized Branch');
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Serialized Branch');
});
