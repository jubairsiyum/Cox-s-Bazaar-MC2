<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'model' => ['nullable', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->whereNull('deleted_at')->ignore($this->route('product')?->id),
            ],
            'storage' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
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
            'brand_id' => 'Brand',
            'category_id' => 'Category',
            'barcode' => 'Barcode / IMEI',
            'purchase_price' => 'Purchase Price',
            'sale_price' => 'Sale Price',
        ];
    }
}
