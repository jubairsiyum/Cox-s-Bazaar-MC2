<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountTransaction;

use App\Enums\AccountTransactionType;
use App\Models\PaymentMethod;
use App\Models\TransactionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationRule;

class UpdateAccountTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('accountTransaction')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entryType = $this->string('type')->toString() ?: null;
        $current = $this->route('accountTransaction');

        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'type' => ['required', new Enum(AccountTransactionType::class)],
            'category' => [
                'required',
                'string',
                'max:50',
                ...TransactionCategory::nameRules($entryType, $current?->category),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            'transaction_date' => ['required', 'date'],
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
            'account_id' => 'account',
            'transaction_date' => 'transaction date',
            'reference_no' => 'reference number',
        ];
    }
}
