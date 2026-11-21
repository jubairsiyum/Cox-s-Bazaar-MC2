<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountMethod;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Default payment methods matching how money moves through the shop,
     * so the Finance "Method" dropdowns are usable out of the box.
     *
     * @var array<string, array{type: AccountMethod, note: string}>
     */
    protected const METHODS = [
        'Cash' => ['type' => AccountMethod::Cash, 'note' => 'Counter cash'],
        'Bank Transfer' => ['type' => AccountMethod::Bank, 'note' => 'BEFTN / RTGS / bank transfer'],
        'bKash/Nagad' => ['type' => AccountMethod::Other, 'note' => 'Mobile financial services'],
        'Cheque' => ['type' => AccountMethod::Other, 'note' => 'Account payee cheque'],
        'Other' => ['type' => AccountMethod::Other, 'note' => 'Anything not covered above'],
    ];

    /**
     * Seed the payment methods table (idempotent).
     */
    public function run(): void
    {
        foreach (self::METHODS as $name => $attributes) {
            $method = PaymentMethod::withTrashed()->firstOrCreate(
                ['name' => $name],
                [
                    'type' => $attributes['type'],
                    'status' => 'active',
                    'note' => $attributes['note'],
                ]
            );

            if ($method->trashed()) {
                $method->restore();
            }
        }
    }
}
