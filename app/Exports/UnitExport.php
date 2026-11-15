<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UnitExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'product_name' => 'Product Name',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'note' => 'Note',
    ];

    /**
     * @var array<string>
     */
    protected array $activeColumns;

    public function __construct(
        protected ?string $search = null,
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
        return Unit::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy('product_name')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  Unit  $unit
     */
    public function map($unit): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'product_name' => $unit->product_name,
                'quantity' => (int) $unit->quantity,
                'unit' => $unit->unit,
                'note' => $unit->note ?: '—',
                default => $unit->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
