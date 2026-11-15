<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Mobile shop product categories (phones, gadget accessories and related items).
     *
     * @var array<string, string|null>
     */
    protected const CATEGORIES = [
        'Smartphones' => 'Android and iOS smartphones',
        'Feature Phones' => 'Keypad and basic mobile phones',
        'Tablets' => 'Tablets and iPads',
        'Phone Cases & Covers' => 'Back covers, flip covers and protective cases',
        'Screen Protectors' => 'Tempered glass and screen guard films',
        'Chargers & Adapters' => 'Wall chargers, car chargers and fast adapters',
        'Power Banks' => 'Portable power banks and battery packs',
        'Earphones & Headphones' => 'Wired and wireless earphones and headphones',
        'Bluetooth Speakers' => 'Portable Bluetooth speakers and audio gadgets',
        'Smart Watches' => 'Smart watches and fitness bands',
        'Memory Cards & OTG' => 'Memory cards, pen drives and OTG adapters',
        'Cables & Converters' => 'USB, Type-C, Lightning cables and converters',
        'Mobile Spare Parts' => 'Displays, batteries and spare parts',
    ];

    /**
     * Seed the categories table.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            Category::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'status' => 'active']
            );
        }
    }
}
