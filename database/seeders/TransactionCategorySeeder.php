<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TransactionCategoryType;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /**
     * Default income/expense categories for a mobile phone shop so the
     * Transactions dropdowns are usable out of the box.
     *
     * @var array<string, array{type: TransactionCategoryType, note: string}>
     */
    protected const CATEGORIES = [
        // Money in
        'Service' => ['type' => TransactionCategoryType::Income, 'note' => 'Service & repair income'],
        'Repair' => ['type' => TransactionCategoryType::Income, 'note' => 'Handset repair jobs'],
        'Scrap Sale' => ['type' => TransactionCategoryType::Income, 'note' => 'Scrap / parts disposal'],
        // Money out
        'Utility' => ['type' => TransactionCategoryType::Expense, 'note' => 'Electricity, water, internet'],
        'Rent' => ['type' => TransactionCategoryType::Expense, 'note' => 'Shop / godown rent'],
        'Salary' => ['type' => TransactionCategoryType::Expense, 'note' => 'Staff salaries'],
        'Courier' => ['type' => TransactionCategoryType::Expense, 'note' => 'Delivery & courier charges'],
        'Packaging' => ['type' => TransactionCategoryType::Expense, 'note' => 'Boxes, bags & packing'],
        'Bank Charge' => ['type' => TransactionCategoryType::Expense, 'note' => 'Bank fees & charges'],
        'Transport' => ['type' => TransactionCategoryType::Expense, 'note' => 'Local transport & fuel'],
        'Marketing' => ['type' => TransactionCategoryType::Expense, 'note' => 'Promotion & advertising'],
        // Either direction
        'Commission' => ['type' => TransactionCategoryType::Both, 'note' => 'Commission earned or paid'],
        'Other' => ['type' => TransactionCategoryType::Both, 'note' => 'Anything not covered above'],
    ];

    /**
     * Seed the transaction categories table (idempotent).
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $attributes) {
            $category = TransactionCategory::withTrashed()->firstOrCreate(
                ['name' => $name],
                [
                    'type' => $attributes['type'],
                    'status' => 'active',
                    'note' => $attributes['note'],
                ]
            );

            if ($category->trashed()) {
                $category->restore();
            }
        }
    }
}
