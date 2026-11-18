<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DuplicateCheckService;
use App\Services\NumberingService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'photo',
        'brand_id',
        'category_id',
        'model',
        'barcode',
        'storage',
        'color',
        'purchase_price',
        'sale_price',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->product_id)) {
                $product->product_id = NumberingService::nextSequential('product_prefix', 'PRD', 4);
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The single stock row holding this product variant's quantity.
     */
    public function stockRow(): HasOne
    {
        return $this->hasOne(Unit::class);
    }

    // ── Accessors ────────────────────────────────────────────

    /**
     * Public URL of the stored product photo, or null when none is set.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->photo ? Storage::disk('public')->url($this->photo) : null,
        );
    }

    // ── Mutators ─────────────────────────────────────────────────────

    /**
     * Normalise identity values so validation, storage and the variant
     * uniqueness rule always agree on what "the same variant" means.
     */
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = DuplicateCheckService::normalize('name', $value) ?? '';
    }

    public function setStorageAttribute(?string $value): void
    {
        $this->attributes['storage'] = DuplicateCheckService::normalize('storage', $value);
    }

    public function setColorAttribute(?string $value): void
    {
        $this->attributes['color'] = DuplicateCheckService::normalize('color', $value);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Human-readable name including the variant (storage / color).
     */
    public function displayName(): string
    {
        $variant = collect([$this->storage, $this->color])
            ->filter()
            ->implode(' / ');

        return $variant === '' ? $this->name : "{$this->name} ({$variant})";
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Validation rule: a (name, storage, color) variant may exist only once,
     * so stock always stays on a single row per product variant.
     *
     * Matching ignores case and surrounding whitespace, always excludes the
     * record being updated ($ignoreId) and soft-deleted records, so editing a
     * product keeps its own name without tripping over itself.
     */
    public static function uniqueVariantRule(?string $storage, ?string $color, ?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($storage, $color, $ignoreId) {
            $exists = Product::query()
                ->matchingVariant((string) $value, $storage, $color)
                ->whereNull('deleted_at')
                ->when($ignoreId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $fail('This product variant already exists (same name, storage and color). Update its quantity instead of adding a duplicate row.');
            }
        };
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Match the single row that represents a (name, storage, color) variant.
     * Comparison is case- and whitespace-insensitive and treats NULL and an
     * empty variant as the same.
     */
    public function scopeMatchingVariant(Builder $query, string $name, ?string $storage, ?string $color): Builder
    {
        return $query
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->whereRaw("LOWER(TRIM(COALESCE(storage, ''))) = ?", [mb_strtolower(trim((string) $storage))])
            ->whereRaw("LOWER(TRIM(COALESCE(color, ''))) = ?", [mb_strtolower(trim((string) $color))]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('product_id', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhere('color', 'like', "%{$term}%")
                ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', "%{$term}%"))
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"));
        });
    }
}
