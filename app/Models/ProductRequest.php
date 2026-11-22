<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductRequestDeliveryStatus;
use App\Enums\ProductRequestStatus;
use App\Services\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request from a branch for a new catalog product. Holds every field of the
 * product form so the Super Admin's approval creates exactly what was asked.
 */
class ProductRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'status',
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
        'quantity',
        'unit',
        'product_status',
        'note',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'created_product_id',
        'delivery_status',
        'delivered_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductRequestStatus::class,
            'delivery_status' => ProductRequestDeliveryStatus::class,
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'quantity' => 'integer',
            'reviewed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'created_product_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ProductRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ProductRequestStatus::Approved;
    }

    public function isInTransit(): bool
    {
        return $this->delivery_status === ProductRequestDeliveryStatus::InTransit;
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === ProductRequestDeliveryStatus::Delivered;
    }

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

    /**
     * The attributes the product is created with on approval. Stock fields
     * (quantity and unit) are kept apart: they belong to the product's stock row.
     *
     * @return array{name: string, brand_id: ?int, category_id: ?int, model: ?string, barcode: ?string, storage: ?string, color: ?string, purchase_price: ?string, sale_price: ?string, status: string, note: ?string, photo: ?string}
     */
    public function toProductAttributes(): array
    {
        return [
            'name' => $this->name,
            'photo' => $this->photo,
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'model' => $this->model,
            'barcode' => $this->barcode,
            'storage' => $this->storage,
            'color' => $this->color,
            'purchase_price' => $this->purchase_price,
            'sale_price' => $this->sale_price,
            'status' => $this->product_status,
            'note' => $this->note,
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────

    /**
     * Requests the viewer may see: the Super Admin sees all; everyone else sees
     * their own branch's requests plus the ones they raised themselves.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (BranchScope::isUnrestricted($user)) {
            return $query;
        }

        $locationIds = BranchScope::accessibleLocationIds($user) ?? [];

        return $query->where(function (Builder $inner) use ($user, $locationIds): void {
            $inner->where('requested_by', $user->id);

            if ($locationIds !== []) {
                $inner->orWhereIn('location_id', $locationIds);
            }
        });
    }
}
