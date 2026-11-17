<?php

declare(strict_types=1);

namespace App\Http\Requests\StockAdjustment;

use App\Enums\StockAdjustmentType;
use App\Models\StockAdjustment;
use App\Models\Unit;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockAdjustment::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('stock_adjustments', 'reference_no')->whereNull('deleted_at')],
            'adjustment_date' => ['required', 'date'],
            'type' => ['required', Rule::enum(StockAdjustmentType::class)],
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', $this->quantityWithinRules()],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference_no' => 'Reference No',
            'adjustment_date' => 'Date',
            'type' => 'Adjustment type',
            'reason' => 'Reason',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Unit Price',
        ];
    }

    /**
     * Quantity rules per operation: add/remove work with positive amounts,
     * a stock count can be zero, and a removal cannot exceed available stock.
     */
    protected function quantityWithinRules(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $type = StockAdjustmentType::tryFrom((string) $this->input('type'));
            $quantity = (int) $value;

            if ($type === StockAdjustmentType::Set) {
                if ($quantity < 0) {
                    $fail('The counted quantity cannot be negative.');
                }

                return;
            }

            if ($quantity < 1) {
                $fail('The quantity must be at least 1.');
            }

            if ($type === StockAdjustmentType::Remove) {
                $index = (int) explode('.', $attribute)[1];
                $available = (int) (Unit::query()->find($this->input("items.{$index}.unit_id"))?->quantity ?? 0);

                if ($quantity > $available) {
                    $fail("Cannot remove more than the available stock ({$available}) for this product.");
                }
            }
        };
    }
}
