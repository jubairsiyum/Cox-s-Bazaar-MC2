<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'field_key',
        'field_type',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerCustomField $field) {
            if (empty($field->field_key)) {
                $baseKey = Str::slug($field->name, '_');
                $key = $baseKey;
                $counter = 1;
                while (static::where('field_key', $key)->exists()) {
                    $key = "{$baseKey}_{$counter}";
                    $counter++;
                }
                $field->field_key = $key;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
