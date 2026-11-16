<?php

declare(strict_types=1);

namespace App\Http\Requests\Location;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Location::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('locations', 'code')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(LocationType::class)],
            'status' => ['nullable', 'in:active,inactive'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Location Name',
            'code' => 'Location Code',
            'type' => 'Type',
            'status' => 'Status',
            'address' => 'Address',
            'phone' => 'Phone',
            'note' => 'Note',
        ];
    }
}
