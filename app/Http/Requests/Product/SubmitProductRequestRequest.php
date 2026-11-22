<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\ProductRequest;

/**
 * A request for a new product. It validates exactly like the Add Product form
 * (same fields and uniqueness rules against the catalog) but is authorized by
 * the request permission rather than the product-creation permission.
 */
class SubmitProductRequestRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductRequest::class) ?? false;
    }
}
