<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SupplierExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'supplier_id' => 'Supplier ID',
        'name' => 'Supplier Name',
        'mobile' => 'Mobile',
        'email' => 'Email',
        'phone' => 'Phone',
        'tax_number' => 'TAX Number',
        'country' => 'Country',
        'state' => 'State',
        'city' => 'City',
        'postcode' => 'Postcode',
        'address' => 'Address',
        'opening_balance' => 'Opening Balance',
        'purchase_due' => 'Purchase Due',
        'purchase_return_due' => 'Purchase Return Due',
        'status' => 'Status',
    ];

    /**
     * @var array<string>
     */
    protected array $activeColumns;

    public function __construct(
        protected ?string $search = null,
        protected ?string $status = null,
        ?string $columns = null,
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
        return Supplier::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  Supplier  $supplier
     */
    public function map($supplier): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'supplier_id' => $supplier->supplier_id,
                'name' => $supplier->name,
                'mobile' => $supplier->mobile ?: '—',
                'email' => $supplier->email ?: '—',
                'phone' => $supplier->phone ?: '—',
                'tax_number' => $supplier->tax_number ?: '—',
                'country' => $supplier->country ?: '—',
                'state' => $supplier->state ?: '—',
                'city' => $supplier->city ?: '—',
                'postcode' => $supplier->postcode ?: '—',
                'address' => $supplier->address ?: '—',
                'opening_balance' => (float) $supplier->opening_balance,
                'purchase_due' => (float) $supplier->purchase_due,
                'purchase_return_due' => (float) $supplier->purchase_return_due,
                'status' => ucfirst($supplier->status),
                default => $supplier->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
