<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@cberp.local'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@cberp.local',
                'password' => 'Admin@1234',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Ensure pre-existing seeded accounts are also verified.
        if (! $admin->hasVerifiedEmail()) {
            $admin->markEmailAsVerified();
        }

        $admin->assignRole('Super Admin');

        $this->command->info('Super Admin created: admin@cberp.local / Admin@1234');
    }
}
