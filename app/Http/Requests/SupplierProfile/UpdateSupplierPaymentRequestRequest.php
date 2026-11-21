<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplierProfile;

use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edit an open payment request the supplier sent: its message, amount and
 * purchase invoice. The branch stays fixed, and the amount and invoice follow
 * the same dues rules as a new request.
 */
class UpdateSupplierPaymentRequestRequest extends FormRequest
{
    use ValidatesPaymentRequestDue;

    /**
     * Only the signed-in supplier's own open requests can be edited: 403 when
     * the request is closed or there is no linked profile, 404 when the request
     * belongs to another supplier.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isSupplierPortalUser()) {
            return false;
        }

        $linkedSupplier = Supplier::query()->where('user_id', $user->id)->first();

        abort_if($linkedSupplier === null, 403, 'No supplier profile is linked to your account.');

        $paymentRequest = $this->route('paymentRequest');

        abort_unless($paymentRequest instanceof SupplierPaymentRequest && (int) $paymentRequest->supplier_id === (int) $linkedSupplier->id, 404);

        return $user->can('editPaymentRequest', [$linkedSupplier, $paymentRequest]);
    }

    protected function prepareForValidation(): void
    {
        /** @var SupplierPaymentRequest $paymentRequest */
        $paymentRequest = $this->route('paymentRequest');

        $this->errorBag = 'updatePaymentRequest-'.$paymentRequest->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SupplierPaymentRequest $paymentRequest */
        $paymentRequest = $this->route('paymentRequest');

        return [
            'purchase_order_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where(fn ($query) => $query
                    ->where('supplier_id', $paymentRequest->supplier_id)
                    ->where('purchase_status', '!=', 'cancelled')),
            ],
            'message' => ['required', 'string', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_order_id' => 'purchase invoice',
            'message' => 'message',
            'amount' => 'amount',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.exists' => 'The selected purchase invoice does not belong to this supplier or is cancelled.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var SupplierPaymentRequest $paymentRequest */
            $paymentRequest = $this->route('paymentRequest');

            $this->validatePaymentRequestDue($validator, $paymentRequest->supplier, $paymentRequest->location_id);
        });
    }
}
