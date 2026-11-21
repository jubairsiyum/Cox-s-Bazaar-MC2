<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
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

    /**
     * Demo-account assignment choice: all four demo accounts join the flagship
     * 'Brand Shop - Cox's Bazar' outlet (code BS01) — one outlet team, so every
     * demo login lands on a branch with stock, sales and finance activity.
     * Strict branch isolation shows unassigned users nothing operational, so
     * a branch assignment is what keeps the demo logins usable. When BS01 is
     * absent the first active location is used instead.
     */
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
                    'branch_id' => $this->demoBranchId(),
                ]
            );

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            if ($user->branch_id === null) {
                $user->update(['branch_id' => $this->demoBranchId()]);
            }

            $user->syncRoles([$role]);
        }

        $this->command->info('Demo users seeded (password: Password@123).');
    }

    /**
     * The demo branch (flagship outlet, falling back to the first active
     * location), or null when no locations are seeded yet.
     */
    private function demoBranchId(): ?int
    {
        $location = Location::query()->active()->where('code', 'BS01')->first()
            ?? Location::query()->active()->where('name', 'Brand Shop - Cox\'s Bazar')->first()
            ?? Location::query()->active()->orderBy('id')->first();

        return $location?->id;
    }
}
