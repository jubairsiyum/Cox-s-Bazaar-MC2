<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomerCollection;

use App\Enums\PaymentMethod;
use App\Enums\SalesInvoiceStatus;
use App\Helpers\Money;
use App\Models\CustomerCollection;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationRule;

class StoreCustomerCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerCollection::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'collection_date' => ['required', 'date'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $allocated = round(collect($this->input('allocations', []))
                        ->sum(fn (array $row) => (float) ($row['amount'] ?? 0)), 2);

                    if (abs($allocated - (float) $value) > 0.01) {
                        $fail('The amount must equal the sum of the invoice allocations.');
                    }
                },
            ],
            'method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => [
                'required',
                'integer',
                'distinct',
                'exists:sales_invoices,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $invoice = SalesInvoice::query()->find($value);

                    if ($invoice === null) {
                        $fail('The selected invoice is invalid.');

                        return;
                    }

                    if ((int) $invoice->customer_id !== (int) $this->input('customer_id')) {
                        $fail('The selected invoice does not belong to the selected customer.');
                    }

                    if ($invoice->status !== SalesInvoiceStatus::Posted) {
                        $fail('Payments can only be recorded against posted invoices.');
                    }
                },
            ],
            'allocations.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $invoiceId = (int) data_get($this->all(), str_replace('.amount', '.sales_invoice_id', $attribute));
                    $invoice = SalesInvoice::query()->find($invoiceId);

                    if ($invoice !== null && (float) $value > (float) $invoice->due_amount + 0.01) {
                        $fail('The allocation cannot exceed the invoice due of '.Money::format($invoice->due_amount).'.');
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
            'collection_date' => 'Collection Date',
            'customer_id' => 'Customer',
            'amount' => 'Amount',
            'method' => 'Payment Method',
            'reference_no' => 'Reference No',
            'location_id' => 'Business Location',
            'allocations' => 'Invoice Allocations',
            'allocations.*.sales_invoice_id' => 'Invoice',
            'allocations.*.amount' => 'Allocation Amount',
        ];
    }
}
