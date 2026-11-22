<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The one place a catalog product is created. The Add Product form and the
 * Super Admin's approval of a product request both go through here, so the
 * product, its single stock row and its audit entry always match.
 */
class ProductService
{
    /**
     * Create a product with its single stock row (the opening quantity).
     *
     * @param  array<string, mixed>  $attributes  Product fields, including created_by.
     */
    public static function create(array $attributes, int $quantity, string $unitOfMeasure): Product
    {
        // Every product is assigned to a branch, defaulting to the Main
        // Warehouse so nothing is ever left "unassigned". The request value
        // arrives as a string, so coerce it to an int for the stock ledger.
        $locationId = $attributes['location_id'] ?? Location::mainWarehouse()?->id;
        $locationId = $locationId !== null ? (int) $locationId : null;

        $product = Product::create([
            ...$attributes,
            'location_id' => $locationId,
        ]);

        // Every product gets a single stock row so it shows up in Units and
        // Transfers. The opening stock lands at the product's own location,
        // not the central pool.
        $stockRow = $product->stockRow()->create([
            'quantity' => 0,
            'unit' => $unitOfMeasure,
            'created_by' => $attributes['created_by'],
        ]);

        if ($quantity > 0) {
            StockService::restock($stockRow, $quantity, $product, 'Opening stock', $locationId);
        }

        AuditService::created($product, "Product '{$product->name}' ({$product->product_id}) created.");

        return $product;
    }

    /**
     * The same uniqueness rules the product form applies (variant name and
     * barcode/IMEI), checked against the current catalog. Null when clear.
     */
    public static function duplicateMessage(string $name, ?string $storage, ?string $color, ?string $barcode): ?string
    {
        $validator = Validator::make(
            ['name' => $name, 'barcode' => $barcode],
            [
                'name' => [Product::uniqueVariantRule($storage, $color)],
                'barcode' => ['nullable', Rule::unique('products', 'barcode')->whereNull('deleted_at')],
            ],
        );

        return $validator->fails() ? $validator->errors()->first() : null;
    }
}
