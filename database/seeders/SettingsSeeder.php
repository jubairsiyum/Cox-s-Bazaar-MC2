<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Company
            ['key' => 'company_name',    'value' => "Cox's Bazaar Mobile Center",  'group' => 'company',  'label' => 'Company Name'],
            ['key' => 'company_address', 'value' => "Cox's Bazaar, Chittagong, Bangladesh", 'group' => 'company', 'label' => 'Address'],
            ['key' => 'company_phone',   'value' => '',                             'group' => 'company',  'label' => 'Phone'],
            ['key' => 'company_email',   'value' => '',                             'group' => 'company',  'label' => 'Email'],
            ['key' => 'company_logo',    'value' => '',                             'group' => 'company',  'label' => 'Logo Path'],

            // Invoice numbering prefixes
            ['key' => 'purchase_order_prefix',  'value' => 'PO',  'group' => 'numbering', 'label' => 'Purchase Order Prefix'],
            ['key' => 'purchase_invoice_prefix','value' => 'PI',  'group' => 'numbering', 'label' => 'Purchase Invoice Prefix'],
            ['key' => 'purchase_return_prefix', 'value' => 'PR',  'group' => 'numbering', 'label' => 'Purchase Return Prefix'],
            ['key' => 'sales_order_prefix',     'value' => 'SO',  'group' => 'numbering', 'label' => 'Sales Order Prefix'],
            ['key' => 'sales_invoice_prefix',   'value' => 'INV', 'group' => 'numbering', 'label' => 'Sales Invoice Prefix'],
            ['key' => 'sales_return_prefix',    'value' => 'SR',  'group' => 'numbering', 'label' => 'Sales Return Prefix'],
            ['key' => 'collection_prefix',      'value' => 'COL', 'group' => 'numbering', 'label' => 'Collection Prefix'],
            ['key' => 'payment_prefix',         'value' => 'PAY', 'group' => 'numbering', 'label' => 'Supplier Payment Prefix'],
            ['key' => 'adjustment_prefix',      'value' => 'ADJ', 'group' => 'numbering', 'label' => 'Stock Adjustment Prefix'],
            ['key' => 'transfer_prefix',        'value' => 'TRF', 'group' => 'numbering', 'label' => 'Stock Transfer Prefix'],
            ['key' => 'expense_prefix',         'value' => 'EXP', 'group' => 'numbering', 'label' => 'Expense Prefix'],
            ['key' => 'income_prefix',          'value' => 'INC', 'group' => 'numbering', 'label' => 'Income Prefix'],

            // System
            ['key' => 'currency_symbol',    'value' => '৳',   'group' => 'system', 'label' => 'Currency Symbol'],
            ['key' => 'currency_code',      'value' => 'BDT', 'group' => 'system', 'label' => 'Currency Code'],
            ['key' => 'low_stock_threshold','value' => '5',   'group' => 'system', 'label' => 'Low Stock Alert Threshold'],
            ['key' => 'allow_negative_stock','value' => '0',  'group' => 'system', 'label' => 'Allow Negative Stock'],
        ];

        foreach ($defaults as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        $this->command->info('Default settings seeded.');
    }
}
