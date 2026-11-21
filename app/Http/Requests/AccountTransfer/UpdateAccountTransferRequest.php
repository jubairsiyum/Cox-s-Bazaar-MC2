<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountTransfer;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationRule;

class UpdateAccountTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('accountTransfer')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $current = $this->route('accountTransfer');

        return [
            'from_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:to_account_id'],
            'to_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'transfer_date' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:20', ...PaymentMethod::nameRules($current?->method)],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_account_id' => 'from account',
            'to_account_id' => 'to account',
            'transfer_date' => 'transfer date',
            'reference_no' => 'reference number',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_account_id.different' => 'A transfer must move money between two different accounts.',
        ];
    }
}
