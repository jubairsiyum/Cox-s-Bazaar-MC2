<?php

use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a verified Super Admin, authenticate as them, and return the user.
 */
function actingAsSuperAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Super Admin');

    test()->actingAs($admin);

    return $admin;
}

/**
 * Create a stock row for a named product variant (the product is created on the fly).
 *
 * @param  array<string, mixed>  $attributes
 */
function stockRowFor(string $productName, array $attributes = []): Unit
{
    $product = Product::factory()->create([
        'name' => $productName,
        'storage' => null,
        'color' => null,
    ]);

    return Unit::factory()->for($product)->create($attributes);
}

/**
 * Move stock from the central pool into a branch location, as a branch that
 * received it would hold it. The overall stock is unchanged; the location now
 * holds up to the requested quantity (capped at what the central pool has).
 */
function stockAtLocation(Unit $unit, int $quantity, int $locationId): void
{
    $quantity = min($quantity, StockService::availableAt($unit, null));

    if ($quantity < 1) {
        return;
    }

    StockService::transferOut($unit, $quantity, null, 'Test stock moved to branch.', null);
    StockService::transferIn($unit, $quantity, null, 'Test stock moved to branch.', $locationId);
}

/**
 * Find the stock row of a named product variant.
 */
function stockRowOf(string $productName): ?Unit
{
    return Unit::query()
        ->whereHas('product', fn ($q) => $q->where('name', $productName))
        ->first();
}
