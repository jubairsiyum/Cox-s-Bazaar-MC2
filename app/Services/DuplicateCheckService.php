<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DuplicateCheckService
{
    /**
     * Identity columns that make two records redundant, in match priority order.
     *
     * @return list<string>
     */
    public static function identityColumns(string $modelClass): array
    {
        return match ($modelClass) {
            Customer::class => ['name', 'mobile', 'tel_no', 'email'],
            Supplier::class => ['name', 'mobile', 'tel_no', 'email', 'tax_number'],
            Brand::class => ['name'],
            Category::class => ['name'],
            Location::class => ['code'],
            default => [],
        };
    }

    /**
     * Normalise an identity value so validation, storage and the unique indexes agree.
     */
    public static function normalize(string $column, mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($column === 'name') {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        return $column === 'email' ? mb_strtolower($value) : $value;
    }

    /**
     * Find active records whose identity overlaps the given values.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, Model> matched column => conflicting record
     */
    public static function liveConflicts(string $modelClass, array $attributes, ?int $ignoreId = null): array
    {
        return self::findMatches($modelClass, $attributes, $ignoreId, trashedOnly: false);
    }

    /**
     * Find soft-deleted records whose identity overlaps the given values.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, Model> matched column => conflicting record
     */
    public static function trashedConflicts(string $modelClass, array $attributes, ?int $ignoreId = null): array
    {
        return self::findMatches($modelClass, $attributes, $ignoreId, trashedOnly: true);
    }

    /**
     * The soft-deleted record to restore and update instead of creating a duplicate.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function reusableRecord(string $modelClass, array $attributes, ?int $ignoreId = null): ?Model
    {
        $matches = self::trashedConflicts($modelClass, $attributes, $ignoreId);

        return $matches === [] ? null : reset($matches);
    }

    /**
     * Permanently remove soft-deleted records whose identity overlaps the given values,
     * so their details can be reused without leaving redundant rows behind.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Model> the permanently removed records
     */
    public static function purgeTrashConflicts(string $modelClass, array $attributes, ?int $keepId = null): Collection
    {
        return collect(self::trashedConflicts($modelClass, $attributes))
            ->unique('id')
            ->reject(fn (Model $record): bool => $keepId !== null && $record->getKey() === $keepId)
            ->each(fn (Model $record) => $record->forceDelete())
            ->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, Model>
     */
    private static function findMatches(string $modelClass, array $attributes, ?int $ignoreId, bool $trashedOnly): array
    {
        $matches = [];

        foreach (self::identityColumns($modelClass) as $column) {
            $value = self::normalize($column, $attributes[$column] ?? null);

            if ($value === null) {
                continue;
            }

            $query = $modelClass::query();

            if ($trashedOnly) {
                $query->onlyTrashed();
            }

            $record = $query
                ->whereRaw('LOWER(TRIM('.$column.')) = ?', [mb_strtolower($value)])
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->orderBy('id')
                ->first();

            if ($record instanceof Model) {
                $matches[$column] = $record;
            }
        }

        return $matches;
    }
}
