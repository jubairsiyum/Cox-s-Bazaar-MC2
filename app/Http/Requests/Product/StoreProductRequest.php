<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Product::uniqueVariantRule($this->input('storage'), $this->input('color'))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'model' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->whereNull('deleted_at')],
            'storage' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50', Rule::in(Unit::UNITS)],
            'status' => ['nullable', 'in:active,inactive'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Product Name',
            'photo' => 'Photo',
            'brand_id' => 'Brand',
            'category_id' => 'Category',
            'barcode' => 'Barcode / IMEI',
            'purchase_price' => 'Purchase Price',
            'sale_price' => 'Sale Price',
            'quantity' => 'Quantity',
            'unit' => 'Unit',
        ];
    }
}
