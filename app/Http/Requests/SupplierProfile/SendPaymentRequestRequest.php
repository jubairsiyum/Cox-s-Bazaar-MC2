<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplierProfile;

use App\Models\Supplier;
use App\Services\BranchScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A payment request sent to a branch on a supplier's behalf from the
 * supplier profile: which branch, an optional purchase order and amount to
 * settle, and the message the branch receives in-app.
 *
 * The branch must owe the supplier money, and the amount may not exceed the
 * due of the chosen purchase order (or of the whole branch when none is
 * chosen). A supplier login may address any branch with dues; staff may only
 * address the branches their branch scope allows.
 */
class SendPaymentRequestRequest extends FormRequest
{
    use ValidatesPaymentRequestDue;

    public function authorize(): bool
    {
        return $this->user()?->can('requestPayment', $this->route('supplier')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->route('supplier');

        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'purchase_order_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where(fn ($query) => $query
                    ->where('supplier_id', $supplier->id)
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
            'location_id' => 'branch',
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
            if ($validator->errors()->has('location_id')) {
                return;
            }

            /** @var Supplier $supplier */
            $supplier = $this->route('supplier');
            $locationId = (int) $this->input('location_id');

            if (! $this->user()->isSupplierPortalUser() && ! BranchScope::canAccess($this->user(), $locationId)) {
                $validator->errors()->add('location_id', 'You can only send payment requests to your own branch.');

                return;
            }

            $this->validatePaymentRequestDue($validator, $supplier, $locationId);
        });
    }
}
