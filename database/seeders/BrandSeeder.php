<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Phone-related brands for the mobile shop.
     *
     * @var array<string, string|null>
     */
    protected const BRANDS = [
        'Samsung' => 'Samsung Galaxy smartphones and tablets',
        'Apple' => 'iPhone, iPad and accessories',
        'Xiaomi' => 'Xiaomi and Redmi smartphones',
        'Redmi' => 'Redmi budget and mid-range smartphones',
        'Realme' => 'Realme smartphones and wearables',
        'Oppo' => 'Oppo smartphones',
        'Vivo' => 'Vivo smartphones',
        'OnePlus' => 'OnePlus flagship smartphones',
        'Nokia' => 'Nokia feature phones and smartphones',
        'Motorola' => 'Motorola smartphones',
        'Google' => 'Google Pixel smartphones',
        'Huawei' => 'Huawei smartphones and tablets',
        'Honor' => 'Honor smartphones',
        'Infinix' => 'Infinix budget smartphones',
        'Tecno' => 'Tecno smartphones',
        'itel' => 'itel feature phones and budget smartphones',
        'Symphony' => 'Symphony smartphones (Bangladesh)',
        'Walton' => 'Walton smartphones and accessories (Bangladesh)',
    ];

    /**
     * Seed the brands table.
     */
    public function run(): void
    {
        foreach (self::BRANDS as $name => $note) {
            $brand = Brand::withTrashed()->firstOrCreate(
                ['name' => $name],
                ['note' => $note]
            );

            if ($brand->trashed()) {
                $brand->restore();
            }
        }
    }
}
