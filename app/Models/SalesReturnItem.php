<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'unit_id',
        'quantity',
        'unit_price',
        'subtotal',
        'condition',
        'restock',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'condition' => ReturnCondition::class,
            'restock' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
