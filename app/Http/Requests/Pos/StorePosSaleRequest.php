<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Models\SalesInvoice;
use App\Services\CustomerResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRule;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesInvoice::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cart_discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'distinct', 'exists:units,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'Customer',
            'customer_name' => 'Customer Name',
            'mobile_no' => 'Mobile No',
            'location_id' => 'Business Location',
            'cart_discount' => 'Discount',
            'paid_amount' => 'Amount Paid',
            'items' => 'Cart',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Rate',
            'items.*.discount' => 'Discount',
        ];
    }

    /**
     * A customer typed on the POS must resolve to a profile that can be stored:
     * a NEW customer needs a mobile number, and neither the name nor the mobile
     * number may already belong to another customer (the mobile number is
     * unique in the customer list). Runs in an `after` hook because the
     * validator skips field rules for nullable fields that are null.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $name = $this->input('customer_name');
                $customerId = filled($this->input('customer_id')) ? (int) $this->input('customer_id') : null;

                if (! filled($name) && $customerId === null) {
                    return;
                }

                if ($customerId === null && CustomerResolver::matchByName($name) === null && blank($this->input('mobile_no'))) {
                    $validator->errors()->add(
                        'mobile_no',
                        'The mobile no is required for a new customer.'
                    );

                    return;
                }

                foreach (CustomerResolver::identityConflicts($customerId, $name, $this->input('mobile_no')) as $field => $conflict) {
                    $validator->errors()->add(
                        $field,
                        __('validation.unique', ['attribute' => $validator->getDisplayableAttribute($field)])
                    );
                }
            },
        ];
    }
}
