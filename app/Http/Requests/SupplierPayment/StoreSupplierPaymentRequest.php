<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplierPayment;

use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationRule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->can('supplier-payments.manage') || $user->can('finance.manage'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => [
                'required',
                'integer',
                Rule::exists('purchase_orders', 'id')->where(fn ($query) => $query
                    ->where('supplier_id', (int) $this->input('supplier_id'))
                    ->where('purchase_status', '!=', 'cancelled')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$this->remainingDue()],
            'payment_date' => ['required', 'date'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'method' => ['nullable', 'string', 'max:20', ...PurchasePayment::methodRules()],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_request_id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_payment_requests', 'id')->where(fn ($query) => $query
                    ->where('supplier_id', (int) $this->input('supplier_id'))
                    ->where('status', 'open')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'purchase_order_id' => 'purchase invoice',
            'amount' => 'payment amount',
            'payment_date' => 'payment date',
            'account_id' => 'paying account',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'The payment amount may not exceed the remaining due of this purchase.',
            'purchase_order_id.exists' => 'The selected purchase invoice does not belong to this supplier or is cancelled.',
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
