<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_no',
        'order_date',
        'expected_date',
        'customer_id',
        'location_id',
        'branch_id',
        'status',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'payment_method',
        'paid_at',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'expected_date' => 'date',
            'status' => SalesOrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesOrder $order) {
            if (blank($order->order_no)) {
                $order->order_no = NumberingService::next('sales_order_prefix', 'SO');
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
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
            $q->where('order_no', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
