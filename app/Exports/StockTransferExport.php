<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\StockTransfer;
use App\Models\User;
use App\Services\BranchScope;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockTransferExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'transfer_date' => 'Date',
        'reference_no' => 'Reference No',
        'from_location' => 'Location (From)',
        'to_location' => 'Location (To)',
        'status' => 'Status',
        'shipping_charges' => 'Shipping Charges',
        'total_amount' => 'Total Amount',
        'additional_notes' => 'Additional Notes',
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
        $query = StockTransfer::query()
            ->with(['fromLocation', 'toLocation'])
            ->when($this->search, fn ($q) => $q->search($this->search));

        // Strict branch isolation: exports honour the user's scope too.
        if ($this->user !== null) {
            $query = BranchScope::scopeQueryAny($query, $this->user, 'from_location_id', 'to_location_id');
        }

        return $query
            ->orderBy('transfer_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  StockTransfer  $transfer
     */
    public function map($transfer): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'transfer_date' => $transfer->transfer_date->format('d/m/Y H:i'),
                'from_location' => $transfer->fromLocation?->name ?? '—',
                'to_location' => $transfer->toLocation?->name ?? '—',
                'status' => $transfer->status->label(),
                'shipping_charges' => number_format((float) $transfer->shipping_charges, 2),
                'total_amount' => number_format((float) $transfer->total_amount, 2),
                'additional_notes' => $transfer->additional_notes ?: '—',
                default => $transfer->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
