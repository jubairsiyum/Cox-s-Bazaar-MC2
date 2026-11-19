<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCollectionAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_collection_id',
        'sales_invoice_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function customerCollection(): BelongsTo
    {
        return $this->belongsTo(CustomerCollection::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
