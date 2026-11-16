<?php

declare(strict_types=1);

namespace App\Http\Requests\StockTransfer;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockTransfer::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('stock_transfers', 'reference_no')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(StockTransferStatus::class)],
            'from_location_id' => ['required', 'integer', 'exists:locations,id'],
            'to_location_id' => ['required', 'integer', 'exists:locations,id', 'different:from_location_id'],
            'shipping_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
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
            'transfer_date' => 'Date',
            'reference_no' => 'Reference No',
            'status' => 'Status',
            'from_location_id' => 'Location (From)',
            'to_location_id' => 'Location (To)',
            'shipping_charges' => 'Shipping Charges',
            'additional_notes' => 'Additional Notes',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Unit Price',
        ];
    }
}
