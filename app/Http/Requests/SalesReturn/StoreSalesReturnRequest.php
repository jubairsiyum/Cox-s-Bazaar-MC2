<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesReturn;

use App\Enums\ReturnCondition;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationRule;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesReturn::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $returnable = $this->returnableQuantities();

        return [
            'sales_invoice_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $invoice = SalesInvoice::query()->find($value);

                    if ($invoice === null) {
                        $fail('The selected invoice is invalid.');

                        return;
                    }

                    if ($invoice->status !== SalesInvoiceStatus::Posted) {
                        $fail('Returns can only be applied to posted invoices.');
                    }
                },
            ],
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $invoice = SalesInvoice::query()->find($this->input('sales_invoice_id'));

                    if ($invoice !== null && (int) $invoice->customer_id !== (int) $value) {
                        $fail('The selected invoice does not belong to the selected customer.');
                    }
                },
            ],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
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
                function (string $attribute, mixed $value, \Closure $fail) use ($returnable): void {
                    if (! $returnable->has((int) $value)) {
                        $fail('The selected product is not part of the chosen invoice.');
                    }
                },
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail) use ($returnable): void {
                    $unitId = (int) data_get($this->all(), str_replace('.quantity', '.unit_id', $attribute));
                    $max = (int) $returnable->get($unitId, 0);

                    if ($max > 0 && (int) $value > $max) {
                        $fail("Return quantity cannot exceed the {$max} invoiced units.");
                    }
                },
            ],
            'items.*.condition' => ['required', 'string', Rule::enum(ReturnCondition::class)],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sales_invoice_id' => 'Invoice Reference',
            'customer_id' => 'Customer',
            'return_date' => 'Date',
            'reason' => 'Reason for Return',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Return Quantity',
            'items.*.condition' => 'Condition',
            'items.*.restock' => 'Restock',
            'items.*.reason' => 'Reason',
        ];
    }

    /**
     * Quantities still returnable from the selected invoice, keyed by unit id:
     * invoiced quantity minus quantities already approved or completed.
     *
     * @return Collection<int, int>
     */
    protected function returnableQuantities(): Collection
    {
        $invoice = SalesInvoice::query()->with('items')->find($this->input('sales_invoice_id'));

        if ($invoice === null) {
            return collect();
        }

        $alreadyReturned = $invoice->returns()
            ->whereIn('status', [SalesReturnStatus::Approved, SalesReturnStatus::Completed])
            ->with('items')
            ->get()
            ->flatMap(fn (SalesReturn $return) => $return->items)
            ->groupBy('unit_id')
            ->map(fn (Collection $items) => (int) $items->sum('quantity'));

        return $invoice->items
            ->mapWithKeys(fn ($item) => [
                (int) $item->unit_id => max(0, (int) $item->quantity - (int) ($alreadyReturned[$item->unit_id] ?? 0)),
            ])
            ->filter(fn (int $quantity) => $quantity > 0);
    }
}
