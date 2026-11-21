<?php

declare(strict_types=1);

namespace App\Exports;

use App\Helpers\Money;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\LocationScope;
use App\Services\SupplierScope;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseOrderExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @var array<string, string>
     */
    protected const COLUMN_DEFINITIONS = [
        'order_date' => 'Date',
        'reference_no' => 'Reference No',
        'location' => 'Location',
        'supplier' => 'Supplier',
        'purchase_status' => 'Purchase Status',
        'payment_status' => 'Payment Status',
        'grand_total' => 'Grand Total',
        'payment_due' => 'Payment Due',
        'added_by' => 'Added By',
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
        $query = PurchaseOrder::query()
            ->with(['supplier', 'location', 'creator'])
            ->when($this->search, fn ($q) => $q->search($this->search));

        // Strict branch isolation: exports honour the user's scope too.
        if ($this->user !== null) {
            $query = SupplierScope::scopeQuery(LocationScope::scopeQuery($query, $this->user), $this->user);
        }

        return $query
            ->orderBy('order_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return array_map(fn ($col) => self::COLUMN_DEFINITIONS[$col], $this->activeColumns);
    }

    /**
     * @param  PurchaseOrder  $order
     */
    public function map($order): array
    {
        $mapped = [];
        foreach ($this->activeColumns as $col) {
            $mapped[] = match ($col) {
                'order_date' => $order->order_date->format('d/m/Y H:i'),
                'location' => $order->location?->name ?? '—',
                'supplier' => $order->supplier?->name ?? '—',
                'purchase_status' => $order->purchase_status->label(),
                'payment_status' => $order->payment_status->label(),
                'grand_total' => Money::format($order->grand_total),
                'payment_due' => Money::format($order->payment_due),
                'added_by' => $order->creator?->name ?? '—',
                default => $order->{$col} ?? '',
            };
        }

        return $mapped;
    }
}
