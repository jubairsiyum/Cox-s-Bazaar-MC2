<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseReturn;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class UpdatePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('purchaseReturn')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var PurchaseReturn $purchaseReturn */
        $purchaseReturn = $this->route('purchaseReturn');
        $purchased = $this->purchasedQuantities();

        return [
            'return_date' => ['required', 'date'],
            'reference_no' => ['nullable', 'string', 'max:50', Rule::unique('purchase_returns', 'reference_no')->ignore($purchaseReturn->id)->whereNull('deleted_at')],
            'purchase_order_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $order = PurchaseOrder::query()->find($value);

                    if ($order === null) {
                        $fail('The selected purchase is invalid.');

                        return;
                    }

                    if ((int) $this->input('supplier_id') !== (int) $order->supplier_id) {
                        $fail('The selected purchase does not belong to the selected distributor.');
                    }
                },
            ],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'reason' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png'],
            'items' => [
                'required',
                'array',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $hasReturn = collect($value)->contains(fn (array $row) => (int) ($row['quantity'] ?? 0) >= 1);

                    if (! $hasReturn) {
                        $fail('Please enter the return quantity for at least one product.');
                    }
                },
            ],
            'items.*.unit_id' => [
                'required',
                'integer',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail) use ($purchased): void {
                    if (! $purchased->has((int) $value)) {
                        $fail('The selected product is not part of the chosen purchase.');
                    }
                },
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($purchased): void {
                    $unitId = (int) data_get($this->all(), str_replace('.quantity', '.unit_id', $attribute));
                    $max = (int) $purchased->get($unitId, 0);

                    if ($max > 0 && (int) $value > $max) {
                        $fail("Return quantity cannot exceed the {$max} units purchased.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'return_date' => 'Date',
            'reference_no' => 'Reference No',
            'purchase_order_id' => 'Invoice Reference',
            'supplier_id' => 'Distributor',
            'reason' => 'Reason for Return',
            'document' => 'Attach Document',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Return Quantity',
        ];
    }

    /**
     * Quantities purchased on the selected purchase, keyed by unit id.
     *
     * @return Collection<int, int>
     */
    private function purchasedQuantities(): Collection
    {
        return PurchaseOrder::query()
            ->find($this->input('purchase_order_id'))
            ?->items()
            ->pluck('quantity', 'unit_id')
            ->map(fn ($quantity) => (int) $quantity) ?? collect();
    }
}
