<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentMethod as PaymentMethodEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationRule;

/**
 * A request to settle the remaining due of a purchase invoice (order).
 *
 * Requests raised by staff start out pending and carry no financial effect;
 * only when a responsible manager (never the requester themselves) approves
 * them is the payment applied to the purchase order and the supplier's
 * purchase due balance. Payments recorded by the responsible manager — or
 * when no other responsible approver exists — are applied and marked
 * approved immediately: a manager is never asked to approve their own task.
 *
 * Method mapping (the clean split between history and new saves):
 *  - legacy rows store App\Enums\PaymentMethod values ('cash', 'bkash', …);
 *    they display through the enum label ('Cash', 'bKash', …) via
 *    methodLabel() and stay accepted by methodRules();
 *  - new saves store the chosen payment_methods reference NAME (the column
 *    is varchar(20)) exactly as displayed in the dropdown, and pass through
 *    methodLabel() unchanged.
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

    /**
     * The supplier payment request this payment actioned, if any.
     */
    public function paymentRequest(): HasOne
    {
        return $this->hasOne(SupplierPaymentRequest::class, 'payment_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * The branch (location) this payment belongs to: the purchase order's
     * location (supplier payments are stamped with the branch they settle).
     */
    public function locationId(): ?int
    {
        return $this->purchaseOrder?->location_id !== null
            ? (int) $this->purchaseOrder->location_id
            : null;
    }

    /**
     * Display label for the stored method: legacy enum values ('cash',
     * 'bkash', …) map to their enum label, stored method names show as-is.
     */
    public function methodLabel(): ?string
    {
        if (blank($this->method)) {
            return null;
        }

        return PaymentMethodEnum::tryFrom(mb_strtolower((string) $this->method))?->label()
            ?? (string) $this->method;
    }

    /**
     * Validation rules for the method field: active payment-method reference
     * names (free-text fallback when nothing is configured, like the transfers
     * form) plus the legacy enum values so historical flows keep working.
     *
     * @return list<ValidationRule>
     */
    public static function methodRules(?string $currentValue = null): array
    {
        $names = array_keys(PaymentMethod::options());

        if ($names === []) {
            return [];
        }

        $allowed = array_merge(
            $names,
            array_map(fn (PaymentMethodEnum $case): string => $case->value, PaymentMethodEnum::cases()),
        );

        if (filled($currentValue) && ! in_array($currentValue, $allowed, true)) {
            $allowed[] = $currentValue;
        }

        return [new In($allowed)];
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

    /**
     * Strict branch isolation: only payments settling purchases of the given
     * location (supplier payments live and die with their purchase order).
     */
    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->whereHas(
            'purchaseOrder',
            fn (Builder $orders) => $orders->where('location_id', $locationId)
        );
    }
}
