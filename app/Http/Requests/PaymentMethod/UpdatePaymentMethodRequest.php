<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentMethod;

use App\Enums\AccountMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('paymentMethod')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:20',
                Rule::unique('payment_methods', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('paymentMethod')?->id),
            ],
            'type' => ['required', new Enum(AccountMethod::class)],
            'status' => ['nullable', 'in:active,inactive'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'method name',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'The method name may not be greater than :max characters (payment columns store up to 20).',
        ];
    }
}
