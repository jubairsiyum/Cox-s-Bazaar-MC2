<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * Create one demo account per role for local development and testing.
     *
     * @var array<string, string>
     */
    private const DEMO_USERS = [
        'Manager' => ['name' => 'Demo Manager',       'email' => 'manager@cberp.local'],
        'Accountant' => ['name' => 'Demo Accountant',    'email' => 'accountant@cberp.local'],
        'Sales Staff' => ['name' => 'Demo Sales Staff',   'email' => 'sales@cberp.local'],
        'Storekeeper' => ['name' => 'Demo Storekeeper',   'email' => 'storekeeper@cberp.local'],
    ];

    public function run(): void
    {
        foreach (self::DEMO_USERS as $role => $details) {
            $user = User::firstOrCreate(
                ['email' => $details['email']],
                [
                    'name' => $details['name'],
                    'email' => $details['email'],
                    'password' => 'Password@123',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->syncRoles([$role]);
        }

        $this->command->info('Demo users seeded (password: Password@123).');
    }
}
