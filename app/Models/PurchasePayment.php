<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to settle the remaining due of a purchase invoice (order).
 *
 * Requests raised by staff start out pending and carry no financial effect;
 * only when a responsible manager (never the requester themselves) approves
 * them is the payment applied to the purchase order and the supplier's
 * purchase due balance. Payments recorded by the responsible manager — or
 * when no other responsible approver exists — are applied and marked
 * approved immediately: a manager is never asked to approve their own task.
 */
class PurchasePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'account_id',
        'amount',
        'payment_date',
        'method',
        'note',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'method' => PaymentMethod::class,
            'status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The cash/bank account the money was paid from (finance-side payments).
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────

    /**
     * Only the most recent request of each purchase order.
     */
    public function scopeLatestPerOrder(Builder $query): Builder
    {
        return $query->whereIn(
            'id',
            self::query()->selectRaw('MAX(id)')->groupBy('purchase_order_id')
        );
    }
}
