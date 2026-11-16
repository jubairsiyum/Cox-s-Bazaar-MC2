<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\StockAdjustment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockAdjustmentExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'adjustment_date' => 'Date',
        'reference_no' => 'Reference No',
        'location' => 'Location',
        'type' => 'Adjustment type',
        'total_amount' => 'Total Amount',
        'total_amount_recovered' => 'Total amount recovered',
        'reason' => 'Reason',
        'creator' => 'Added By',
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
        return StockAdjustment::query()
            ->with(['location', 'creator'])
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy('adjustment_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  StockAdjustment  $adjustment
     */
    public function map($adjustment): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'adjustment_date' => $adjustment->adjustment_date->format('d/m/Y H:i'),
                'location' => $adjustment->location?->name ?? '—',
                'type' => $adjustment->type->label(),
                'total_amount' => number_format((float) $adjustment->total_amount, 2),
                'total_amount_recovered' => number_format((float) $adjustment->total_amount_recovered, 2),
                'reason' => $adjustment->reason ?: '—',
                'creator' => $adjustment->creator?->name ?? '—',
                default => $adjustment->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
