<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    public const UNITS = ['Pcs', 'Box', 'Dozen', 'Pack', 'Carton', 'Pair', 'Set'];

    protected $fillable = [
        'product_id',
        'quantity',
        'unit',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Record the opening stock movement for every new stock row, stamped
        // at the creating user's own branch (strict branch isolation).
        static::created(function (Unit $unit) {
            $quantity = (int) $unit->quantity;

            if ($quantity > 0) {
                $unit->stockMovements()->create([
                    'location_id' => StockService::actorLocationId(),
                    'type' => StockMovementType::Opening,
                    'quantity' => $quantity,
                    'quantity_after' => $quantity,
                    'note' => 'Opening stock',
                    'created_by' => $unit->created_by,
                ]);
            }
        });

        // Model-level guard: a direct write may never take stock below zero.
        // StockService writes quietly and enforces the same rule itself.
        static::updating(function (Unit $unit) {
            $new = (int) $unit->quantity;

            if ($unit->isDirty('quantity') && $new < 0 && ! StockService::allowsNegativeStock()) {
                $old = (int) $unit->getOriginal('quantity');

                throw InsufficientStockException::for($unit->displayName(), $old, $old - $new);
            }
        });

        // Safety net: any quantity change made outside StockService still
        // lands in the ledger so stock always equals the sum of its movements.
        // StockService uses updateQuietly() so its typed movements are not
        // double-recorded here.
        static::updated(function (Unit $unit) {
            if (! $unit->wasChanged('quantity')) {
                return;
            }

            $old = (int) $unit->getOriginal('quantity');
            $new = (int) $unit->quantity;

            $unit->stockMovements()->create([
                'location_id' => StockService::actorLocationId(),
                'type' => StockMovementType::Adjustment,
                'quantity' => $new - $old,
                'quantity_after' => $new,
                'note' => 'Stock adjusted',
                'created_by' => Auth::id() ?? $unit->created_by,
            ]);
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Human-readable product name (including the variant) for this stock row.
     */
    public function displayName(): string
    {
        return $this->product?->displayName() ?? 'Unknown product';
    }

    /**
     * Whether the product is at or below the configured low stock threshold.
     */
    public function isLowStock(): bool
    {
        return $this->quantity <= (int) Setting::get('low_stock_threshold', 5);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('units.unit', 'like', "%{$term}%")
                ->orWhere('units.note', 'like', "%{$term}%")
                ->orWhereHas('product', fn (Builder $p) => $p->search($term));
        });
    }
}
