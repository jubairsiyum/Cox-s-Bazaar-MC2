<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order matters: permissions before users.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class, // must be first
            SettingsSeeder::class,
            SuperAdminSeeder::class,
            BranchSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            UnitSeeder::class,
            LocationSeeder::class,
            DemoUsersSeeder::class, // after locations: demo accounts join a branch
            SupplierAccountSeeder::class, // after roles: supplier login linked to its profile
            AccountSeeder::class,
            TransactionCategorySeeder::class,
            PaymentMethodSeeder::class,
        ]);
    }
}
