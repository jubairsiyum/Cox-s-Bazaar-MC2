<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationRule;

/**
 * Reference data for the "method" dropdown of Finance forms (transactions,
 * account transfers, supplier payments).
 *
 * Entries store the chosen method name on their own string column (no FK) so
 * history keeps displaying its stored value even after a method is
 * deactivated or renamed. `type` mirrors the coarse AccountMethod bucket
 * (cash|bank|other) for reporting.
 */
class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountMethod::class,
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * How many finance entries store this method name across transactions,
     * account transfers and supplier payments.
     */
    public function usageCount(): int
    {
        $name = mb_strtolower($this->name);

        return AccountTransaction::query()->whereRaw('LOWER(method) = ?', [$name])->count()
            + AccountTransfer::query()->whereRaw('LOWER(method) = ?', [$name])->count()
            + PurchasePayment::query()->whereRaw('LOWER(method) = ?', [$name])->count();
    }

    // ─── Scopes ───────────────────────────────────────────────────

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
                ->orWhere('note', 'like', "%{$term}%");
        });
    }

    // ─── Shared dropdown / validation source ───────────────────────

    /**
     * Active method names (name => name) for Finance dropdowns. Inactive rows
     * never appear here — they drop out of dropdowns while old entries keep
     * showing their stored value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * Validation rules restricting a stored method name to the active
     * reference rows. When nothing is configured yet the field stays free
     * text, so legacy entries and fresh installs keep working.
     *
     * @return list<ValidationRule>
     */
    public static function nameRules(?string $currentValue = null): array
    {
        $names = array_keys(static::options());

        if (filled($currentValue) && ! in_array($currentValue, $names, true)) {
            $names[] = $currentValue;
        }

        return $names === [] ? [] : [new In($names)];
    }
}
