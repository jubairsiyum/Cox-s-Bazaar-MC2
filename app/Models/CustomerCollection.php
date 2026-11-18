<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCollection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'collection_no',
        'customer_id',
        'collection_date',
        'amount',
        'method',
        'reference_no',
        'note',
        'location_id',
        'branch_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'collection_date' => 'datetime',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerCollection $collection) {
            if (blank($collection->collection_no)) {
                $collection->collection_no = NumberingService::next('collection_prefix', 'COL');
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerCollectionAllocation::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(SalesInvoice::class, 'customer_collection_allocations')
            ->withPivot('amount')
            ->withTimestamps();
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

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('collection_no', 'like', "%{$term}%")
                ->orWhere('reference_no', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
