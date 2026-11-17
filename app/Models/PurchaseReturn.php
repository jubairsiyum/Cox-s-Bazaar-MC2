<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_no',
        'purchase_order_id',
        'return_date',
        'supplier_id',
        'location_id',
        'reason',
        'payment_status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'paid_amount',
        'document',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'datetime',
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseReturn $return) {
            if (blank($return->reference_no)) {
                $return->reference_no = NumberingService::next('purchase_return_prefix', 'PR');
            }
        });

        static::saving(function (PurchaseReturn $return) {
            $return->payment_status = PaymentStatus::fromAmount($return->paid_amount, $return->grand_total);
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Accessors ────────────────────────────────────────────────────

    /**
     * Outstanding amount the supplier still owes for this return.
     */
    protected function paymentDue(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round(max(0, (float) $this->grand_total - (float) $this->paid_amount), 2),
        );
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
                ->orWhereHas('purchaseOrder', fn (Builder $p) => $p->where('reference_no', 'like', "%{$term}%"))
                ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"))
                ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
