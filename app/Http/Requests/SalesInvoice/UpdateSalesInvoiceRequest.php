<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesInvoice;

use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRule;

class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('salesInvoice');

        return $invoice instanceof SalesInvoice
            && ($this->user()?->can('update', $invoice) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_date' => ['required', 'date'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'distinct', 'exists:units,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'invoice_date' => 'Invoice Date',
            'customer_id' => 'Customer',
            'sales_order_id' => 'Sales Order',
            'location_id' => 'Business Location',
            'tax_rate' => 'Tax Rate',
            'paid_amount' => 'Amount Paid',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Rate',
            'items.*.discount' => 'Discount',
        ];
    }
}
