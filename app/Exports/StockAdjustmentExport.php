<?php

declare(strict_types=1);

namespace App\Exports;

use App\Helpers\Money;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\LocationScope;
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
        'type' => 'Adjustment type',
        'total_amount' => 'Total Amount',
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
        $query = StockAdjustment::query()
            ->with('creator')
            ->when($this->search, fn ($q) => $q->search($this->search));

        // Strict branch isolation: exports honour the user's scope too.
        if ($this->user !== null) {
            $query = LocationScope::scopeQuery($query, $this->user);
        }

        return $query
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
                'type' => $adjustment->type->label(),
                'total_amount' => Money::format($adjustment->total_amount),
                'reason' => $adjustment->reason ?: '—',
                'creator' => $adjustment->creator?->name ?? '—',
                default => $adjustment->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
