<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomerExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'customer_id' => 'Customer ID',
        'name' => 'Customer Name',
        'mobile' => 'Mobile',
        'phone' => 'Phone',
        'email' => 'Email',
        'city' => 'City',
        'state' => 'State',
        'country' => 'Country',
        'postcode' => 'Postcode',
        'address' => 'Address',
        'note' => 'Note',
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
        return Customer::query()
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
     * @param  Customer  $customer
     */
    public function map($customer): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'customer_id' => $customer->customer_id,
                'name' => $customer->name,
                'mobile' => $customer->mobile ?: '—',
                'phone' => $customer->phone ?: '—',
                'email' => $customer->email ?: '—',
                'city' => $customer->city ?: '—',
                'state' => $customer->state ?: '—',
                'country' => $customer->country ?: '—',
                'postcode' => $customer->postcode ?: '—',
                'address' => $customer->address ?: '—',
                'note' => $customer->note ?: '—',
                'status' => ucfirst($customer->status),
                default => $customer->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
