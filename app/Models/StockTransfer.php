<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Services\LocationScope;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_no',
        'transfer_date',
        'status',
        'from_location_id',
        'to_location_id',
        'shipping_charges',
        'total_amount',
        'additional_notes',
        'created_by',
        'approved_by',
        'approved_at',
        'is_request',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'from_location_id' => 'integer',
            'to_location_id' => 'integer',
            'transfer_date' => 'datetime',
            'status' => StockTransferStatus::class,
            'shipping_charges' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'is_request' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockTransfer $transfer) {
            if (blank($transfer->reference_no)) {
                $transfer->reference_no = NumberingService::next('transfer_prefix', 'TRF');
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Status as seen by the given viewer.
     *
     * A draft or in-transit transfer awaiting the receiving manager's decision
     * reads "Awaiting Approval" on the receiving side. Once the receiving
     * manager approves, the transfer reads "Received" for the receiving side
     * and "Delivered" for the sending side. A rejected transfer stays visible
     * as "Rejected"; a transfer cancelled by the sender reads "Cancelled".
     *
     * @return array{value: string, label: string}
     */
    public function displayStatusFor(?User $user): array
    {
        $senderSide = $user !== null
            && LocationScope::canAccess($user, $this->from_location_id)
            && ! LocationScope::canAccess($user, $this->to_location_id);

        $receiverSide = $user !== null
            && LocationScope::canAccess($user, $this->to_location_id)
            && ! LocationScope::canAccess($user, $this->from_location_id);

        $sourceSide = $user !== null && LocationScope::canAccess($user, $this->from_location_id);

        return match ($this->status) {
            StockTransferStatus::Requested => $sourceSide
                ? ['value' => 'awaiting-approval', 'label' => 'Awaiting Approval']
                : ['value' => 'requested', 'label' => 'Requested'],
            StockTransferStatus::Received => $senderSide
                ? ['value' => 'delivered', 'label' => 'Delivered']
                : ['value' => 'received', 'label' => 'Received'],
            StockTransferStatus::InTransit => $receiverSide
                ? ['value' => 'awaiting-approval', 'label' => 'Awaiting Approval']
                : ['value' => 'in-transit', 'label' => 'In Transit'],
            StockTransferStatus::Draft => match (true) {
                $receiverSide => ['value' => 'awaiting-approval', 'label' => 'Awaiting Approval'],
                $senderSide => ['value' => 'pending', 'label' => 'Pending'],
                default => ['value' => 'draft', 'label' => 'Draft'],
            },
            StockTransferStatus::Rejected => ['value' => 'rejected', 'label' => 'Rejected'],
            StockTransferStatus::Cancelled => ['value' => 'cancelled', 'label' => 'Cancelled'],
        };
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('reference_no', 'like', "%{$term}%")
                ->orWhere('additional_notes', 'like', "%{$term}%")
                ->orWhereHas('fromLocation', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"))
                ->orWhereHas('toLocation', fn (Builder $l) => $l->where('name', 'like', "%{$term}%"));
        });
    }
}
