<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_no',
        'order_date',
        'supplier_id',
        'location_id',
        'purchase_status',
        'payment_status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'paid_amount',
        'document',
        'created_by',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'purchase_status' => PurchaseStatus::class,
            'payment_status' => PaymentStatus::class,
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order) {
            if (blank($order->reference_no)) {
                $order->reference_no = NumberingService::next('purchase_order_prefix', 'PO');
            }
        });

        static::saving(function (PurchaseOrder $order) {
            $order->payment_status = PaymentStatus::fromAmount($order->paid_amount, $order->grand_total);
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Accessors ────────────────────────────────────────────────────

    /**
     * Outstanding amount still payable to the supplier.
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
                ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', "%{$term}%"))
                ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
