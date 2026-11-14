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
    public function __construct(
        protected ?string $search = null,
        protected ?string $status = null,
    ) {}

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
        return [
            'Supplier ID',
            'Supplier Name',
            'Mobile',
            'Email',
            'Phone',
            'GST Number',
            'TAX Number',
            'Country',
            'State',
            'City',
            'Postcode',
            'Address',
            'Opening Balance',
            'Purchase Due',
            'Purchase Return Due',
            'Status',
        ];
    }

    /**
     * @param  Supplier  $supplier
     */
    public function map($supplier): array
    {
        return [
            $supplier->supplier_id,
            $supplier->name,
            $supplier->mobile,
            $supplier->email,
            $supplier->phone,
            $supplier->gst_number,
            $supplier->tax_number,
            $supplier->country,
            $supplier->state,
            $supplier->city,
            $supplier->postcode,
            $supplier->address,
            $supplier->opening_balance,
            $supplier->purchase_due,
            $supplier->purchase_return_due,
            $supplier->status,
        ];
    }
}
