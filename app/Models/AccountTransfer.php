<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountEntryStatus;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A transfer of money from one cash/bank account to another.
 *
 * Like account transactions, transfers start out pending and only move money
 * between the two accounts once completed.
 */
class AccountTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'location_id',
        'amount',
        'transfer_date',
        'method',
        'reference_no',
        'note',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'from_account_id' => 'integer',
            'to_account_id' => 'integer',
            'location_id' => 'integer',
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
            'status' => AccountEntryStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AccountTransfer $transfer) {
            if (blank($transfer->reference_no)) {
                $transfer->reference_no = NumberingService::next('transfer_prefix', 'TRF');
            }

            // Strict branch isolation: the transfer is stamped with the branch
            // it happened at — the sending account's location, falling back to
            // the creator's own branch (see BranchScope).
            if ($transfer->location_id === null) {
                $transfer->location_id = $transfer->fromAccount?->location_id
                    ?? User::query()->whereKey($transfer->created_by)->value('branch_id');
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    /**
     * The branch (location) this transfer happened at, when known. NULL marks
     * shared/head-office activity visible to the Super Admin only.
     */
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

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference_no', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%");
        });
    }
}
