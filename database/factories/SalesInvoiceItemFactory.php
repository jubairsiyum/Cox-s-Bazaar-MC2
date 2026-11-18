<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesInvoiceItem>
 */
class SalesInvoiceItemFactory extends Factory
{
    protected $model = SalesInvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = (float) fake()->numberBetween(50, 500);

        return [
            'sales_invoice_id' => SalesInvoice::factory(),
            'unit_id' => Unit::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'subtotal' => round($quantity * $unitPrice, 2),
        ];
    }
}
