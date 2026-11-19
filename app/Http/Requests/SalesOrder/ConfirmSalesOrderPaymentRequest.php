<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrder;

use App\Enums\PaymentMethod;
use App\Helpers\Money;
use App\Models\SalesOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payment capture on the sales order's "Confirm Payment & Generate Invoice"
 * step. The business never sells on credit: the amount received must equal
 * the order total exactly — partial (and over-) payments are rejected.
 */
class ConfirmSalesOrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('salesOrder');

        return $order instanceof SalesOrder
            && ($this->user()?->can('update', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'amount_received' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * Full payment is required: the received amount has to equal the order total.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('amount_received')) {
                    return;
                }

                /** @var SalesOrder $order */
                $order = $this->route('salesOrder');

                if (abs(round((float) $this->input('amount_received'), 2) - round((float) $order->grand_total, 2)) > 0.01) {
                    $validator->errors()->add(
                        'amount_received',
                        'The received amount must equal the order total ('.Money::format($order->grand_total).'). Full payment is required — partial payments are not accepted.'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment_method' => 'Payment Method',
            'amount_received' => 'Amount Received',
        ];
    }
}
