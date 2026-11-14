<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'mobile',
        'tel_no',
        'email',
        'country',
        'state',
        'city',
        'postcode',
        'address',
        'note',
        'custom_fields',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->customer_id)) {
                $customer->customer_id = NumberingService::nextSequential('customer_prefix', 'CU', 4);
            }
        });
    }

    // ── Relationships ──────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->tel_no;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['tel_no'] = $value;
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
                ->orWhere('customer_id', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('tel_no', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%");
        });
    }
}
