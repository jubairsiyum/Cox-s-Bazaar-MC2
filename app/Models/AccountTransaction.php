<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountEntryStatus;
use App\Enums\AccountTransactionType;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A money-in (income) or money-out (expense) entry on a cash/bank account.
 *
 * Entries start out pending and only touch the account balance once a
 * qualified approver completes them (see App\Services\AccountBalanceService).
 */
class AccountTransaction extends Model
{
    use HasFactory;

    /**
     * Suggested categories for a mobile phone shop's income/expense entries.
     * Legacy list kept for reference data seeding — dropdowns now come from
     * the TransactionCategory table (see TransactionCategory::options()).
     *
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'Utility', 'Rent', 'Salary', 'Repair', 'Courier', 'Commission',
        'Transport', 'Marketing', 'Bank Charge', 'Service', 'Scrap Sale', 'Other',
    ];

    protected $fillable = [
        'account_id',
        'location_id',
        'type',
        'category',
        'amount',
        'transaction_date',
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
            'account_id' => 'integer',
            'location_id' => 'integer',
            'type' => AccountTransactionType::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'status' => AccountEntryStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AccountTransaction $transaction) {
            if (blank($transaction->reference_no)) {
                $transaction->reference_no = $transaction->type === AccountTransactionType::Income
                    ? NumberingService::next('income_prefix', 'INC')
                    : NumberingService::next('expense_prefix', 'EXP');
            }

            // Strict branch isolation: the entry is stamped with the branch it
            // happened at — the account's location, falling back to the
            // creator's own branch (see BranchScope).
            if ($transaction->location_id === null) {
                $transaction->location_id = $transaction->account?->location_id
                    ?? User::query()->whereKey($transaction->created_by)->value('branch_id');
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The branch (location) this entry happened at, when known. NULL marks
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

    // ─── Helpers ──────────────────────────────────────────────────

    public function isIncome(): bool
    {
        return $this->type === AccountTransactionType::Income;
    }

    /**
     * Signed balance effect of this entry: positive for money in, negative out.
     */
    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->isIncome() ? $amount : -$amount;
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('category', 'like', "%{$term}%")
                ->orWhere('reference_no', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%");
        });
    }
}
