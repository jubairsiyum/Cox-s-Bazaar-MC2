<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrder;

use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\CustomerResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesOrderRequest extends FormRequest
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
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'distinct', 'exists:units,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Reject customer details that already belong to another customer —
     * name and phone are unique identities. Conflicts with the customer the
     * submission resolves to are fine (that profile is reused/updated).
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

                foreach (CustomerResolver::identityConflicts(
                    (int) $this->input('customer_id') ?: null,
                    $this->input('customer_name'),
                    $this->input('customer_phone')
                ) as $field => $conflict) {
                    $validator->errors()->add(
                        $field,
                        __('validation.unique', ['attribute' => $validator->getDisplayableAttribute($field)])
                    );
                }

                // Verify stock availability at the selected location unless negative stock is allowed in settings
                $allowsNegative = (bool) Setting::get('allow_negative_stock', '0');
                if (! $allowsNegative) {
                    $locationId = (int) $this->input('location_id');

                    foreach ($this->input('items', []) as $index => $item) {
                        $unitId = (int) ($item['unit_id'] ?? 0);
                        $quantity = (int) ($item['quantity'] ?? 0);

                        if ($unitId > 0 && $quantity > 0) {
                            $unit = Unit::query()->find($unitId);
                            if ($unit) {
                                $available = SalesOrderController::resolveLocationStock($unit, $locationId);
                                if ($quantity > $available) {
                                    $validator->errors()->add(
                                        "items.{$index}.quantity",
                                        "Quantity ({$quantity}) exceeds available stock ({$available}) of '{$unit->displayName()}' at the selected location."
                                    );
                                }
                            }
                        }
                    }
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
            'order_date' => 'Order Date',
            'expected_date' => 'Expected Date',
            'customer_id' => 'Customer',
            'customer_name' => 'Customer Name',
            'customer_phone' => 'Phone Number',
            'location_id' => 'Business Location',
            'tax_rate' => 'Tax Rate',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Rate',
            'items.*.discount' => 'Discount',
        ];
    }
}
