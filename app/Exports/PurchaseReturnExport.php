<?php

declare(strict_types=1);

namespace App\Exports;

use App\Helpers\Money;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Services\LocationScope;
use App\Services\SupplierScope;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseReturnExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'return_date' => 'Date',
        'reference_no' => 'Reference No',
        'parent_purchase' => 'Parent Purchase',
        'location' => 'Location',
        'supplier' => 'Supplier',
        'reason' => 'Reason',
        'payment_status' => 'Payment Status',
        'grand_total' => 'Grand Total',
        'payment_due' => 'Payment Due',
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
        $query = PurchaseReturn::query()
            ->with(['purchaseOrder', 'supplier', 'location'])
            ->when($this->search, fn ($q) => $q->search($this->search));

        // Strict branch isolation: exports honour the user's scope too.
        if ($this->user !== null) {
            $query = SupplierScope::scopeQuery(LocationScope::scopeQuery($query, $this->user), $this->user);
        }

        return $query
            ->orderBy('return_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  PurchaseReturn  $return
     */
    public function map($return): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'return_date' => $return->return_date->format('d/m/Y H:i'),
                'parent_purchase' => $return->purchaseOrder?->reference_no ?? '—',
                'location' => $return->location?->name ?? '—',
                'supplier' => $return->supplier?->name ?? '—',
                'reason' => $return->reason ?: '—',
                'payment_status' => $return->payment_status->label(),
                'grand_total' => Money::format($return->grand_total),
                'payment_due' => Money::format($return->payment_due),
                default => $return->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
