<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierPaymentRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment request a supplier sends to a branch (recorded by staff on the
 * supplier's behalf from the supplier profile).
 *
 * The targeted branch receives an in-app notification linking to the
 * Finance > Supplier Payments recording screen; once the payment is recorded
 * the request turns "actioned" and links to the payment that settled it.
 */
class SupplierPaymentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'location_id',
        'purchase_order_id',
        'message',
        'amount',
        'status',
        'created_by',
        'actioned_by',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => SupplierPaymentRequestStatus::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class, 'payment_id');
    }

    // ── Accessors ────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === SupplierPaymentRequestStatus::Open;
    }
}
