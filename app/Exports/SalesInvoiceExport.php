<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\SalesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesInvoiceExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'invoice_no' => 'Invoice No',
        'invoice_date' => 'Date',
        'customer' => 'Customer',
        'location' => 'Branch/Location',
        'items_count' => 'Items',
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'tax' => 'Tax',
        'grand_total' => 'Grand Total',
        'paid' => 'Paid',
        'due' => 'Due',
        'payment_status' => 'Payment Status',
        'status' => 'Status',
    ];

    /**
     * @var array<string>
     */
    protected array $activeColumns;

    /**
     * @param  array{customer_id?: int|string, status?: string, payment_status?: string, date_from?: string, date_to?: string, location_id?: int|string, branch_id?: int|string}  $filters  Register filters, mirroring the invoice index.
     */
    public function __construct(
        protected ?string $search = null,
        ?string $columns = null,
        protected array $filters = [],
        protected ?User $user = null,
    ) {
        if (! empty($columns)) {
            $requested = array_filter(array_map('trim', explode(',', $columns)));
            $this->activeColumns = array_values(array_intersect($requested, array_keys(self::COLUMN_DEFINITIONS)));
        }

        if (empty($this->activeColumns)) {
            $this->activeColumns = array_keys(self::COLUMN_DEFINITIONS);
        }
    }

    public function collection(): Collection
    {
        return $this->filteredQuery()
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  SalesInvoice  $invoice
     */
    public function map($invoice): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'invoice_date' => $invoice->invoice_date->format('d/m/Y H:i'),
                'customer' => $invoice->customer?->name ?? 'Walk-in',
                'location' => collect([$invoice->branch?->name, $invoice->location?->name])->filter()->implode(' / ') ?: '—',
                'items_count' => (int) ($invoice->items_count ?? 0),
                'subtotal' => number_format((float) $invoice->subtotal, 2),
                'discount' => number_format((float) $invoice->discount_amount, 2),
                'tax' => number_format((float) $invoice->tax_amount, 2),
                'grand_total' => number_format((float) $invoice->grand_total, 2),
                'paid' => number_format((float) $invoice->paid_amount, 2),
                'due' => number_format((float) $invoice->payment_due, 2),
                'payment_status' => $invoice->payment_status->label(),
                'status' => $invoice->status->label(),
                default => $invoice->{$col} ?? '',
            };
        }

        return $mapped;
    }

    /**
     * Base query with every register filter applied.
     * Outlet Managers only see invoices of their branch/location.
     */
    private function filteredQuery(): Builder
    {
        $query = SalesInvoice::query()
            ->with(['customer', 'location', 'branch'])
            ->withCount('items')
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filters['customer_id'] ?? null, fn ($q) => $q->where('customer_id', (int) $this->filters['customer_id']))
            ->when($this->filters['status'] ?? null, fn ($q) => $q->where('status', (string) $this->filters['status']))
            ->when($this->filters['payment_status'] ?? null, fn ($q) => $q->where('payment_status', (string) $this->filters['payment_status']))
            ->when($this->filters['date_from'] ?? null, fn ($q) => $q->whereDate('invoice_date', '>=', (string) $this->filters['date_from']))
            ->when($this->filters['date_to'] ?? null, fn ($q) => $q->whereDate('invoice_date', '<=', (string) $this->filters['date_to']));

        if ($this->user !== null) {
            $query = SalesScope::scopeQuery($query, $this->user);
        }

        return $query
            ->when($this->filters['location_id'] ?? null, fn ($q) => $q->where('location_id', (int) $this->filters['location_id']))
            ->when($this->filters['branch_id'] ?? null, fn ($q) => $q->where('branch_id', (int) $this->filters['branch_id']));
    }
}
