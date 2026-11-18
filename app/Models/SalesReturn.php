<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesReturnStatus;
use App\Services\NumberingService;
use App\Services\SalesScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_no',
        'sales_invoice_id',
        'customer_id',
        'return_date',
        'location_id',
        'branch_id',
        'status',
        'reason',
        'refund_amount',
        'approved_at',
        'restocked_at',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'datetime',
            'status' => SalesReturnStatus::class,
            'refund_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'restocked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesReturn $return) {
            if (blank($return->return_no)) {
                $return->return_no = NumberingService::next('sales_return_prefix', 'SR');
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Dynamic status wording: the responsible manager sees "Awaiting Approval"
     * on pending returns while everyone else reads "Pending Review".
     *
     * @return array{key: string, label: string}
     */
    public function displayStatusFor(?User $user): array
    {
        if ($this->status === SalesReturnStatus::Pending) {
            $responsible = $user !== null
                && ($user->isSuperAdmin() || $user->hasAnyRole(['Manager', 'Outlet Manager']));

            return $responsible
                ? ['key' => 'awaiting-approval', 'label' => 'Awaiting Approval']
                : ['key' => 'pending', 'label' => 'Pending Review'];
        }

        return ['key' => $this->status->badge(), 'label' => $this->status->label()];
    }

    /**
     * Whether the given user may accept or reject this return.
     */
    public function canBeApprovedBy(?User $user): bool
    {
        return $user !== null
            && $this->status === SalesReturnStatus::Pending
            && SalesScope::canApprove($user, $this);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('return_no', 'like', "%{$term}%")
                ->orWhere('reason', 'like', "%{$term}%")
                ->orWhereHas('salesInvoice', fn (Builder $i) => $i->where('invoice_no', 'like', "%{$term}%"))
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
