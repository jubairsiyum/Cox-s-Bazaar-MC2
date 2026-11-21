<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseStatus;
use App\Models\PurchaseOrder;
use App\Services\LocationScope;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('purchaseOrder')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PurchaseOrder $order */
        $order = $this->route('purchaseOrder');
        $approved = $order->approval_status === ApprovalStatus::Approved;

        // Effective (approved) purchases move inside the state machine:
        // "Ordered" places the order with the supplier (displayed as "Pending"
        // until the supplier accepts), "Received" posts the goods and
        // "Cancelled" reverses a receipt. "Pending" is a system-driven
        // display state and never selectable. Legacy documents still awaiting
        // sign-off keep their pre-receipt options — "Received" is only
        // reachable for them through the approval action.
        $allowedStatuses = $approved
            ? [
                PurchaseStatus::Ordered->value,
                PurchaseStatus::Received->value,
                PurchaseStatus::Cancelled->value,
            ]
            : [PurchaseStatus::Pending->value, PurchaseStatus::Ordered->value, PurchaseStatus::Cancelled->value];

        $rules = [
            'order_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('purchase_orders', 'reference_no')->ignore($order->id)->whereNull('deleted_at')],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id', $this->accessibleLocationRule()],
            'purchase_status' => ['required', Rule::in($allowedStatuses)],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png'],
        ];

        // Line items are only editable before the goods are received; once a
        // receipt has posted stock for the stored quantities they are frozen.
        if (! $order->purchase_status->hasReceived()) {
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
     * Strict branch isolation: purchases may only be edited for the user's
     * own branch (the Super Admin may purchase for any branch).
     */
    protected function accessibleLocationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->user() === null || ! LocationScope::canAccess($this->user(), $value)) {
                $fail('You may only manage purchases of your own branch.');
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
