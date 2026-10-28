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
        ]);
    }
}
