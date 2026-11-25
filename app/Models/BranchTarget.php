<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesInvoiceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'target_month',
        'target_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Calculated Helpers ───────────────────────────────────────────

    /**
     * Total posted sales achieved for this location during this target month.
     */
    public function achievedSales(): float
    {
        $startDate = Carbon::createFromFormat('!Y-m', $this->target_month)->startOfMonth();
        $endDate = Carbon::createFromFormat('!Y-m', $this->target_month)->endOfMonth();

        return (float) SalesInvoice::query()
            ->where('location_id', $this->location_id)
            ->where('status', SalesInvoiceStatus::Posted)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('grand_total');
    }

    /**
     * Achievement percentage (e.g. 75.5%).
     */
    public function achievementPercentage(): float
    {
        $target = (float) $this->target_amount;
        if ($target <= 0.0) {
            return 0.0;
        }

        return round(($this->achievedSales() / $target) * 100, 2);
    }

    /**
     * Remaining amount to reach target (floored at zero).
     */
    public function remainingAmount(): float
    {
        $target = (float) $this->target_amount;
        $achieved = $this->achievedSales();

        return max(0.0, round($target - $achieved, 2));
    }
}
