<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplierProfile;

use App\Models\Supplier;
use App\Services\DuplicateCheckService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The details a supplier login may edit on its own profile: identity, contact
 * and address. Code, status, balances, the login link and branch data are
 * never accepted here.
 */
class UpdateOwnSupplierProfileRequest extends FormRequest
{
    protected $errorBag = 'updateSupplierDetails';

    /**
     * A supplier login may update only the supplier profile linked to it: 403
     * without a linked profile, 404 when the route names another supplier.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->isSupplierPortalUser()) {
            return false;
        }

        $linkedSupplier = Supplier::query()->where('user_id', $user->id)->first();

        abort_if($linkedSupplier === null, 403, 'No supplier profile is linked to your account.');

        $supplier = $this->route('supplier');

        abort_unless($supplier instanceof Supplier && (int) $supplier->id === (int) $linkedSupplier->id, 404);

        return $user->can('updateOwnProfile', $linkedSupplier);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tel_no' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Reject details that already belong to another supplier.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Supplier $supplier */
                $supplier = $this->route('supplier');

                foreach (array_keys(DuplicateCheckService::liveConflicts(Supplier::class, $this->validated(), $supplier->getKey())) as $column) {
                    $validator->errors()->add(
                        $column,
                        __('validation.unique', ['attribute' => $validator->getDisplayableAttribute($column)])
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Supplier Name',
            'mobile' => 'Mobile No.',
            'tel_no' => 'Tel No.',
            'tax_number' => 'TAX Number',
        ];
    }
}
