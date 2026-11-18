<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesPaymentStatus;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'customer_id',
        'sales_order_id',
        'location_id',
        'branch_id',
        'status',
        'payment_status',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'credit_amount',
        'paid_amount',
        'due_amount',
        'note',
        'void_reason',
        'posted_at',
        'voided_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'datetime',
            'status' => SalesInvoiceStatus::class,
            'payment_status' => SalesPaymentStatus::class,
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesInvoice $invoice) {
            if (blank($invoice->invoice_no)) {
                $invoice->invoice_no = NumberingService::next('sales_invoice_prefix', 'INV');
            }
        });

        static::saving(function (SalesInvoice $invoice) {
            // due = grand total − return credits − payments, floored at zero;
            // payments beyond that stay as a customer advance (paid_amount).
            // A voided invoice is settled and never collects anything.
            $invoice->due_amount = $invoice->status === SalesInvoiceStatus::Voided
                ? 0.0
                : round(
                    max(0, (float) $invoice->grand_total - (float) $invoice->credit_amount - (float) $invoice->paid_amount),
                    2
                );

            $invoice->payment_status = SalesPaymentStatus::derive(
                $invoice->status,
                $invoice->paid_amount,
                $invoice->due_amount
            );
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerCollectionAllocation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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

    // ── Accessors ────────────────────────────────────────────────────

    /**
     * Outstanding amount the customer still owes for this invoice.
     */
    protected function paymentDue(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round(max(0, (float) $this->due_amount), 2),
        );
    }

    /**
     * Net invoice value after approved return credits.
     */
    protected function netTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round((float) $this->grand_total - (float) $this->credit_amount, 2),
        );
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('invoice_no', 'like', "%{$term}%")
                ->orWhere('note', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('location', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
