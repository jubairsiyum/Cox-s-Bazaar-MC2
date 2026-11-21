<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\PurchaseStatus;
use App\Models\PurchaseOrder;
use App\Services\LocationScope;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * Receiving statuses selectable at entry time: "Ordered" places the order
     * with the supplier (the manager side then displays "Pending" until the
     * supplier accepts) and "Received" is a direct receipt of goods already
     * taken. "Pending" is a system-driven display state, "Cancelled" a
     * lifecycle end state (edit / delete) — neither is an entry state, and
     * "Partially Received" does not exist because receipts are posted for
     * whole orders.
     *
     * @return array<string>
     */
    public static function entryStatuses(): array
    {
        return [
            PurchaseStatus::Ordered->value,
            PurchaseStatus::Received->value,
        ];
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
            'location_id' => ['required', 'integer', 'exists:locations,id', $this->accessibleLocationRule()],
            'purchase_status' => ['required', Rule::in(self::entryStatuses())],
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
     * Strict branch isolation: purchases may only be recorded for the user's
     * own branch (the Super Admin may purchase for any branch).
     */
    protected function accessibleLocationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->user() === null || ! LocationScope::canAccess($this->user(), $value)) {
                $fail('You may only record purchases for your own branch.');
            }
        };
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
            'purchase_status' => 'Purchase Status',
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
