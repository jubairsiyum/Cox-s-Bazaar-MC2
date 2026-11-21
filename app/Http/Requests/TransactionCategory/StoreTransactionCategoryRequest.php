<?php

declare(strict_types=1);

namespace App\Http\Requests\TransactionCategory;

use App\Enums\TransactionCategoryType;
use App\Models\TransactionCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TransactionCategory::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('transaction_categories', 'name')->whereNull('deleted_at')],
            'type' => ['required', new Enum(TransactionCategoryType::class)],
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
            'name' => 'category name',
        ];
    }
}
