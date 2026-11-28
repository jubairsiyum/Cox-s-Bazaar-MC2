<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'closing_date',
        'opening_balance',
        'cash_sales',
        'cash_collections',
        'cash_expenses',
        'cash_remittances',
        'system_cash',
        'counted_cash',
        'variance',
        'variance_status',
        'status',
        'note',
        'closed_by',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'opening_balance' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'cash_collections' => 'decimal:2',
            'cash_expenses' => 'decimal:2',
            'cash_remittances' => 'decimal:2',
            'system_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'reopened_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public static function isDateClosed(int $locationId, CarbonInterface|string $date): bool
    {
        $dateString = is_string($date) ? $date : $date->toDateString();

        return static::query()
            ->where('location_id', $locationId)
            ->whereBetween('closing_date', [$dateString, $dateString.' 23:59:59'])
            ->where('status', 'closed')
            ->exists();
    }
}
