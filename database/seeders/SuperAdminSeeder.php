<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@cberp.local'],
            [
                'name'     => 'Super Admin',
                'email'    => 'admin@cberp.local',
                'password' => Hash::make('Admin@1234'),
                'status'   => 'active',
            ]
        );

        $admin->assignRole('Super Admin');

        $this->command->info("Super Admin created: admin@cberp.local / Admin@1234");
    }
}
