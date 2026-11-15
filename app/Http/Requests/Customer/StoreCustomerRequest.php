<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && ! $this->has('tel_no')) {
            $this->merge(['tel_no' => $this->input('phone')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:50'],
            'tel_no' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:active,inactive'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*.label' => ['nullable', 'string', 'max:100'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Customer Name',
            'mobile' => 'Mobile No.',
            'tel_no' => 'Tel No.',
            'phone' => 'Tel No.',
        ];
    }
}
