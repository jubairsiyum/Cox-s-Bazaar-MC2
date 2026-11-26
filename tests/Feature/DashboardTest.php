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

it('does not expose removed tailadmin sample routes or sidebar menu items', function () {
    actingAsSuperAdmin();

    $this->get('/ecommerce')->assertNotFound();
    $this->get('/calendar')->assertNotFound();
    $this->get('/alerts')->assertNotFound();
    $this->get('/buttons')->assertNotFound();
    $this->get('/form-elements')->assertNotFound();
    $this->get('/basic-tables')->assertNotFound();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('TailAdmin Components')
        ->assertDontSee('Ecommerce Demo');
});

it('serves compatible CSP headers on auth pages in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        expect($csp)
            ->toContain("'unsafe-eval'")
            ->toContain("style-src 'self' 'unsafe-inline'");

        preg_match("/'nonce-([^']+)'/", $csp, $matches);
        expect($matches[1] ?? '')->not->toBe('');
        $response->assertSee('nonce="'.$matches[1].'"', false);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
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
