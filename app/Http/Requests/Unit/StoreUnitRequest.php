<?php

declare(strict_types=1);

namespace App\Http\Requests\Unit;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Unit::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:255', Product::uniqueVariantRule($this->input('storage'), $this->input('color'))],
            'storage' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:50', Rule::in(Unit::UNITS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_name' => 'Product Name',
            'storage' => 'Storage',
            'color' => 'Color',
            'quantity' => 'Quantity',
            'unit' => 'Unit',
            'note' => 'Note',
        ];
    }
}
