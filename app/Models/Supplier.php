<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DuplicateCheckService;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/**
 * Suppliers have no logins by default — their "profile" is a page inside the
 * admin app that staff use on the supplier's behalf. A supplier may be linked
 * to a user with the Supplier role (user_id), which lets them open their own
 * profile. Notifiable powers the supplier's own activity feed (database
 * notifications addressed to the supplier row).
 */
class Supplier extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'user_id',
        'name',
        'mobile',
        'email',
        'tel_no',
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

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(SupplierPaymentRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The login account of a supplier with the Supplier role, when one is linked.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = DuplicateCheckService::normalize('name', $value) ?? '';
    }

    public function setMobileAttribute(?string $value): void
    {
        $this->attributes['mobile'] = DuplicateCheckService::normalize('mobile', $value);
    }

    public function setTelNoAttribute(?string $value): void
    {
        $this->attributes['tel_no'] = DuplicateCheckService::normalize('tel_no', $value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = DuplicateCheckService::normalize('email', $value);
    }

    public function setTaxNumberAttribute(?string $value): void
    {
        $this->attributes['tax_number'] = DuplicateCheckService::normalize('tax_number', $value);
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->tel_no;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->tel_no = $value;
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
                ->orWhere('tel_no', 'like', "%{$term}%")
                ->orWhere('tax_number', 'like', "%{$term}%");
        });
    }
}
