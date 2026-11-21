<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseDue;

use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRule;

class StorePurchaseDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchasePayment::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$this->remainingDue()],
            'method' => ['nullable', 'string', 'max:20', ...PurchasePayment::methodRules()],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_order_id' => 'purchase invoice',
            'amount' => 'payment amount',
            'method' => 'payment method',
            'note' => 'note',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'The payment amount may not exceed the remaining due of this purchase.',
        ];
    }

    /**
     * Remaining due of the referenced purchase order, or zero when unknown.
     */
    private function remainingDue(): float
    {
        $order = PurchaseOrder::query()->find($this->input('purchase_order_id'));

        return $order?->payment_due ?? 0.0;
    }
}
