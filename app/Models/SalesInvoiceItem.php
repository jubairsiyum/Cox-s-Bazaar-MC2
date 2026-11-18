<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id',
        'unit_id',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
