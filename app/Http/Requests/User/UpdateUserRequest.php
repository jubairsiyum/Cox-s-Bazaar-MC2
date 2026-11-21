<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DuplicateCheckService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'status' => ['required', new Enum(UserStatus::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')],
            'supplier_name' => [Rule::requiredIf(fn (): bool => $this->hasSupplierRole()), 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasSupplierRole() || blank($this->input('supplier_name'))) {
                return;
            }

            /** @var User $user */
            $user = $this->route('user');

            $conflicts = DuplicateCheckService::liveConflicts(Supplier::class, [
                'name' => $this->input('supplier_name'),
                'mobile' => $this->input('phone'),
                'email' => $this->input('email'),
            ], $user->supplier?->id);

            foreach (array_keys($conflicts) as $column) {
                $validator->errors()->add('supplier_name', __('validation.unique', ['attribute' => $column]));
            }
        });
    }

    private function hasSupplierRole(): bool
    {
        return in_array('Supplier', (array) $this->input('roles', []), true);
    }
}
