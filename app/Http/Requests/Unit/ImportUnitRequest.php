<?php

declare(strict_types=1);

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Unit::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ];
    }
}
