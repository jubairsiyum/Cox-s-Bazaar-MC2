<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionCategoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationRule;

/**
 * Reference data for the "category" dropdown of income/expense entries.
 *
 * Transaction entries store the chosen category name on their own string
 * column (no FK) so history keeps displaying its stored value even after a
 * category is deactivated or renamed.
 */
class TransactionCategory extends Model
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
            'type' => TransactionCategoryType::class,
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
     * How many income/expense entries store this category name.
     */
    public function usageCount(): int
    {
        return AccountTransaction::query()
            ->whereRaw('LOWER(category) = ?', [mb_strtolower($this->name)])
            ->count();
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Narrow categories to those usable on the given entry type
     * ("income" / "expense"); a blank entry type keeps them all.
     */
    public function scopeForEntryType(Builder $query, ?string $entryType): Builder
    {
        if (blank($entryType)) {
            return $query;
        }

        $allowed = collect(TransactionCategoryType::cases())
            ->filter(fn (TransactionCategoryType $type) => $type->allowsEntry($entryType))
            ->pluck('value')
            ->all();

        return $query->whereIn('type', $allowed);
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
     * Active category names (name => name) for Finance dropdowns, optionally
     * narrowed to the categories usable on an entry type. Inactive rows never
     * appear here — they drop out of dropdowns while old entries keep showing
     * their stored value.
     *
     * @return array<string, string>
     */
    public static function options(?string $entryType = null): array
    {
        return static::query()
            ->active()
            ->forEntryType($entryType)
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    /**
     * Validation rules restricting a stored category name to the active
     * reference rows. When nothing is configured yet the field stays free
     * text, so legacy entries and fresh installs keep working.
     *
     * @return list<ValidationRule>
     */
    public static function nameRules(?string $entryType = null, ?string $currentValue = null): array
    {
        $names = array_keys(static::options($entryType));

        if (filled($currentValue) && ! in_array($currentValue, $names, true)) {
            $names[] = $currentValue;
        }

        return $names === [] ? [] : [new In($names)];
    }
}
