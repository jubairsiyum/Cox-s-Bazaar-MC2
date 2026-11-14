<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'name',
        'mobile',
        'email',
        'phone',
        'tax_number',
        'opening_balance',
        'purchase_due',
        'purchase_return_due',
        'country',
        'state',
        'city',
        'postcode',
        'address',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'purchase_due' => 'decimal:2',
            'purchase_return_due' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            if (empty($supplier->supplier_id)) {
                $supplier->supplier_id = NumberingService::nextSequential('supplier_prefix', 'SP', 4);
            }
        });
    }

    // ── Relationships ──────────────────────────────────────

    public function openingPayments(): HasMany
    {
        return $this->hasMany(SupplierOpeningPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ── Scopes ─────────────────────────────────────────────

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
                ->orWhere('supplier_id', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('tax_number', 'like', "%{$term}%");
        });
    }
}
