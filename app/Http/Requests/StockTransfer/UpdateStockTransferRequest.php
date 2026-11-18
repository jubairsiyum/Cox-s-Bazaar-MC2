<?php

declare(strict_types=1);

namespace App\Http\Requests\StockTransfer;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Services\LocationScope;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('stockTransfer')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var StockTransfer $transfer */
        $transfer = $this->route('stockTransfer');
        $status = $transfer->status;

        // "Received" is only reachable through the receiving manager's approval.
        $allowedStatuses = collect(StockTransferStatus::transitionsFrom($status))
            ->reject(fn (StockTransferStatus $s) => $s === StockTransferStatus::Received && $status !== StockTransferStatus::Received)
            ->map(fn (StockTransferStatus $s) => $s->value)
            ->all();

        $rules = [
            'transfer_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('stock_transfers', 'reference_no')->whereNull('deleted_at')->ignore($transfer->id)],
            'status' => ['required', Rule::in($allowedStatuses)],
            'from_location_id' => ['required', 'integer', 'exists:locations,id', $this->accessibleFromLocationRule()],
            'to_location_id' => ['required', 'integer', 'exists:locations,id', 'different:from_location_id'],
            'shipping_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
        ];

        // Line items are only editable while the transfer is still a draft;
        // once stock has moved, quantities are frozen.
        if ($status === StockTransferStatus::Draft) {
            $rules = array_merge($rules, [
                'items' => ['required', 'array', 'min:1'],
                'items.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            ]);
        }

        return $rules;
    }

    /**
     * Outlet Managers may only dispatch stock from their own location.
     */
    protected function accessibleFromLocationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->user() === null || ! LocationScope::canAccess($this->user(), $value)) {
                $fail('You may only dispatch stock from your own location.');
            }
        };
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
