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
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('stock_adjustments', 'reference_no')->whereNull('deleted_at')->ignore($adjustment->id)],
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
     * Quantity rules per operation. The document's own effect is restored
     * before it is re-posted, so a line's available stock is the level the
     * document originally adjusted from.
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
                $unitId = $this->input("items.{$index}.unit_id");

                $existingItem = StockAdjustmentItem::query()
                    ->where('stock_adjustment_id', $this->route('stockAdjustment')?->id)
                    ->where('unit_id', $unitId)
                    ->first();

                $available = (int) ($existingItem?->quantity_before ?? Unit::query()->find($unitId)?->quantity ?? 0);

                if ($quantity > $available) {
                    $fail("Cannot remove more than the available stock ({$available}) for this product.");
                }
            }
        };
    }
}
