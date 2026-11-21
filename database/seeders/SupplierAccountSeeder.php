<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SupplierAccountSeeder extends Seeder
{
    /**
     * Demo supplier login: signs in with the Supplier role and opens the
     * "Realme Hub Bangladesh" supplier profile. The supplier record is reused
     * when it exists (only its user link is set), otherwise it is created.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'supplier@cberp.local'],
            [
                'name' => 'Realme Hub Bangladesh',
                'password' => 'password@123',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        Role::firstOrCreate(['name' => 'Supplier', 'guard_name' => 'web']);

        $user->syncRoles(['Supplier']);

        $supplier = Supplier::query()->firstOrCreate(
            ['name' => 'Realme Hub Bangladesh'],
            ['status' => 'active']
        );

        $supplier->update(['user_id' => $user->id]);

        $this->command->info('Supplier account seeded (email: supplier@cberp.local, password: password@123).');
    }
}
