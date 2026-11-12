<?php

declare(strict_types=1);

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.company_name' => ['nullable', 'string', 'max:255'],
            'settings.company_address' => ['nullable', 'string', 'max:255'],
            'settings.company_phone' => ['nullable', 'string', 'max:50'],
            'settings.company_email' => ['nullable', 'email', 'max:255'],
            'settings.company_logo' => ['nullable', 'string', 'max:255'],
            'settings.purchase_order_prefix' => ['nullable', 'string', 'max:20'],
            'settings.purchase_invoice_prefix' => ['nullable', 'string', 'max:20'],
            'settings.purchase_return_prefix' => ['nullable', 'string', 'max:20'],
            'settings.sales_order_prefix' => ['nullable', 'string', 'max:20'],
            'settings.sales_invoice_prefix' => ['nullable', 'string', 'max:20'],
            'settings.sales_return_prefix' => ['nullable', 'string', 'max:20'],
            'settings.collection_prefix' => ['nullable', 'string', 'max:20'],
            'settings.payment_prefix' => ['nullable', 'string', 'max:20'],
            'settings.adjustment_prefix' => ['nullable', 'string', 'max:20'],
            'settings.transfer_prefix' => ['nullable', 'string', 'max:20'],
            'settings.expense_prefix' => ['nullable', 'string', 'max:20'],
            'settings.income_prefix' => ['nullable', 'string', 'max:20'],
            'settings.currency_symbol' => ['nullable', 'string', 'max:10'],
            'settings.currency_code' => ['nullable', 'string', 'max:10'],
            'settings.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'settings.allow_negative_stock' => ['nullable', 'in:0,1'],
        ];
    }

    /**
     * Normalize the allow_negative_stock checkbox into a stored value.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $data['settings']['allow_negative_stock'] = (string) ($data['settings']['allow_negative_stock'] ?? '0');
        }

        return $data;
    }
}
