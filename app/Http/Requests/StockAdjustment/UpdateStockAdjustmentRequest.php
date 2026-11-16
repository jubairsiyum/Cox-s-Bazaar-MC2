<?php

declare(strict_types=1);

namespace App\Http\Requests\StockAdjustment;

use App\Enums\StockAdjustmentType;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Unit;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('stockAdjustment')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->route('stockAdjustment');

        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('stock_adjustments', 'reference_no')->whereNull('deleted_at')->ignore($adjustment->id)],
            'adjustment_date' => ['required', 'date'],
            'type' => ['required', Rule::enum(StockAdjustmentType::class)],
            'total_amount_recovered' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', $this->recoveredWithinTotal()],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:type,abnormal'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', $this->abnormalWithinStock()],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'location_id' => 'Business Location',
            'reference_no' => 'Reference No',
            'adjustment_date' => 'Date',
            'type' => 'Adjustment type',
            'total_amount_recovered' => 'Total amount recovered',
            'reason' => 'Reason',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
            'items.*.unit_price' => 'Unit Price',
        ];
    }

    /**
     * An abnormal adjustment cannot remove more than the available stock.
     * The document's own removals are restored before it is re-posted, so the
     * level it originally adjusted from is the one that counts.
     */
    protected function abnormalWithinStock(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->input('type') !== StockAdjustmentType::Abnormal->value) {
                return;
            }

            $index = (int) explode('.', $attribute)[1];
            $unitId = $this->input("items.{$index}.unit_id");

            $existingItem = StockAdjustmentItem::query()
                ->where('stock_adjustment_id', $this->route('stockAdjustment')?->id)
                ->where('unit_id', $unitId)
                ->first();

            $available = (int) ($existingItem?->quantity_before ?? Unit::query()->find($unitId)?->quantity ?? 0);

            if ((int) $value > $available) {
                $fail("Cannot remove more than the available stock ({$available}) for this product.");
            }
        };
    }

    /**
     * The recovered amount cannot exceed the value of the adjusted goods.
     */
    protected function recoveredWithinTotal(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $total = collect($this->input('items', []))->sum(
                fn (array $item) => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)
            );

            if ((float) $value > $total) {
                $fail('The total amount recovered cannot exceed the total amount of the adjustment.');
            }
        };
    }
}
