<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
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
        'supplier_status',
        'accepted_at',
        'delivered_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'purchase_status' => PurchaseStatus::class,
            'payment_status' => PaymentStatus::class,
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
            'supplier_status' => SupplierOrderStatus::class,
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(SupplierPaymentRequest::class);
    }

    // ── Status displays ──────────────────────────────────────────────

    /**
     * Manager-facing status badge: the branch side reads "Pending" while the
     * supplier has not accepted a placed order, "Ordered" once accepted and
     * "Received" once the branch marks the goods received, whatever the
     * payment state. The approval dimension wins for legacy rows created
     * before purchases took effect at creation.
     *
     * @return array{0: string, 1: string} [badge status key, label]
     */
    public function managerDisplayStatus(): array
    {
        return match (true) {
            $this->approval_status === ApprovalStatus::Rejected => ['rejected', ApprovalStatus::Rejected->label()],
            $this->purchase_status === PurchaseStatus::Cancelled => ['cancelled', PurchaseStatus::Cancelled->label()],
            $this->approval_status === ApprovalStatus::Pending => ['pending', 'Awaiting Approval'],
            $this->purchase_status === PurchaseStatus::Received => ['received', PurchaseStatus::Received->label()],
            $this->purchase_status === PurchaseStatus::Ordered
                && $this->supplier_status !== SupplierOrderStatus::Pending => ['ordered', PurchaseStatus::Ordered->label()],
            default => ['pending', PurchaseStatus::Pending->label()],
        };
    }

    /**
     * Supplier-facing status badge. "Delivered" once the supplier side is
     * Delivered (accepted and goods received by the branch). Until then the
     * supplier sees "Pending" before accepting and "Accepted" after, never
     * "Received".
     * The accepted badge reuses the "ordered" key for the info badge style.
     *
     * @return array{0: string, 1: string} [badge status key, label]
     */
    public function supplierDisplayStatus(): array
    {
        if ($this->supplier_status === SupplierOrderStatus::Delivered) {
            return ['delivered', 'Delivered'];
        }

        [$status, $label] = $this->managerDisplayStatus();

        if (! in_array($status, ['ordered', 'received'], true)) {
            return [$status, $label];
        }

        return $this->supplier_status === SupplierOrderStatus::Pending
            ? ['pending', 'Pending']
            : ['ordered', 'Accepted'];
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
