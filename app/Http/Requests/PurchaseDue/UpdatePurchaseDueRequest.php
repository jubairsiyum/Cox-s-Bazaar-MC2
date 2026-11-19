<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseDue;

use App\Enums\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationRule;

class UpdatePurchaseDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('purchasePayment')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$this->remainingDue()],
            'method' => ['nullable', new Enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
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
     * Remaining due of the payment's purchase order, counting the pending
     * request itself back in so its own amount stays valid.
     */
    private function remainingDue(): float
    {
        /** @var PurchasePayment|null $payment */
        $payment = $this->route('purchasePayment');

        if ($payment === null) {
            return 0.0;
        }

        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::query()->find($payment->purchase_order_id);

        return round(($order?->payment_due ?? 0.0) + (float) $payment->amount, 2);
    }
}
