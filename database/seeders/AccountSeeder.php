<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Default cash/bank accounts so the Finance section is usable out of the box.
     *
     * @var array<string, array{type: AccountType, account_number: ?string, bank_name: ?string, bank_branch: ?string, note: string}>
     */
    protected const ACCOUNTS = [
        'Cash in Hand' => [
            'type' => AccountType::Cash,
            'account_number' => null,
            'bank_name' => null,
            'bank_branch' => null,
            'note' => 'Main shop counter cash box',
        ],
        'City Bank - Cox\'s Bazar' => [
            'type' => AccountType::Bank,
            'account_number' => '0000-000000-000',
            'bank_name' => 'The City Bank PLC',
            'bank_branch' => "Cox's Bazar",
            'note' => 'Primary business bank account',
        ],
    ];

    /**
     * Seed the accounts table.
     */
    public function run(): void
    {
        foreach (self::ACCOUNTS as $name => $attributes) {
            $account = Account::withTrashed()->firstOrCreate(
                ['name' => $name, 'type' => $attributes['type']],
                [
                    'account_number' => $attributes['account_number'],
                    'bank_name' => $attributes['bank_name'],
                    'bank_branch' => $attributes['bank_branch'],
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'status' => 'active',
                    'note' => $attributes['note'],
                ]
            );

            if ($account->trashed()) {
                $account->restore();
            }
        }
    }
}
