<?php

declare(strict_types=1);

namespace App\Http\Requests\StockTransfer;

use App\Models\StockTransfer;
use App\Services\BranchScope;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchStockRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && BranchScope::locationId($this->user()) !== null
            && $this->user()->can('createRequest', StockTransfer::class);
    }

    /**
     * Requests carry no upper quantity limit: they ask the source to send
     * stock, and availability is checked when the source approves.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('status', 'active')->whereNull('deleted_at'),
                $this->otherBranchRule(),
            ],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * A branch may only ask other locations, never itself.
     */
    protected function otherBranchRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->user() === null || (int) $value === BranchScope::locationId($this->user())) {
                $fail('You cannot request stock from your own branch.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_location_id' => 'Request From',
            'additional_notes' => 'Note',
            'items' => 'Products',
            'items.*.unit_id' => 'Product',
            'items.*.quantity' => 'Quantity',
        ];
    }
}
