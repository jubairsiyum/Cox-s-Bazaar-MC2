<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('purchase_orders', 'reference_no')->whereNull('deleted_at')],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'order_date' => 'Date',
            'reference_no' => 'Reference No',
            'supplier_id' => 'Supplier',
            'location_id' => 'Business Location',
            'tax_rate' => 'Purchase Tax',
            'paid_amount' => 'Amount Paid',
            'document' => 'Attach Document',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Unit Price',
        ];
    }
}
