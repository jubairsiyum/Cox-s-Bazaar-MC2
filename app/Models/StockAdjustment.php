<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockAdjustmentType;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_no',
        'adjustment_date',
        'type',
        'total_amount',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'datetime',
            'type' => StockAdjustmentType::class,
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockAdjustment $adjustment) {
            if (blank($adjustment->reference_no)) {
                $adjustment->reference_no = NumberingService::next('adjustment_prefix', 'ADJ');
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Whether every adjusted stock row still sits at the quantity this
     * document posted — i.e. nothing has moved since, so the adjustment can
     * still be edited or reversed safely.
     */
    public function isReversible(): bool
    {
        return $this->items->every(fn (StockAdjustmentItem $item) => (int) $item->unit?->quantity === (int) $item->quantity_after);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference_no', 'like', "%{$term}%")
                ->orWhere('reason', 'like', "%{$term}%")
                ->orWhereHas('items', fn (Builder $i) => $i->whereHas('unit', fn (Builder $u) => $u->search($term)));
        });
    }
}
