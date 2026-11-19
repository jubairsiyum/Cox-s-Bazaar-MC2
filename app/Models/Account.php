<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cash or bank account holding the shop's money.
 *
 * `current_balance` is denormalized for fast KPI reads; it only moves through
 * completed account transactions, account transfers and supplier payments
 * (see App\Services\AccountBalanceService).
 */
class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'account_number',
        'bank_name',
        'bank_branch',
        'opening_balance',
        'current_balance',
        'status',
        'location_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'to_account_id');
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function isCash(): bool
    {
        return $this->type === AccountType::Cash;
    }

    public function isBank(): bool
    {
        return $this->type === AccountType::Bank;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeType(Builder $query, AccountType|string $type): Builder
    {
        return $query->where('type', $type instanceof AccountType ? $type->value : $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('account_number', 'like', "%{$term}%")
                ->orWhere('bank_name', 'like', "%{$term}%")
                ->orWhere('bank_branch', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%");
        });
    }
}
