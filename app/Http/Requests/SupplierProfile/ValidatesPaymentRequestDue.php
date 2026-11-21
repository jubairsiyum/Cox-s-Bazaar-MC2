<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplierProfile;

use App\Helpers\Money;
use App\Models\Supplier;
use App\Services\SupplierDuesBoard;
use Illuminate\Validation\Validator;

/**
 * The dues rules shared by sending and editing a payment request: the branch
 * must owe the supplier money, the purchase invoice (when chosen) must belong
 * to that branch and still carry a due, and the amount may not exceed the due
 * it settles.
 */
trait ValidatesPaymentRequestDue
{
    protected function validatePaymentRequestDue(Validator $validator, Supplier $supplier, int $locationId): void
    {
        $branch = SupplierDuesBoard::branches($supplier)->firstWhere('location_id', $locationId);

        if ($branch === null || $branch['due'] <= 0) {
            $validator->errors()->add('location_id', 'This branch has no outstanding dues with the supplier.');

            return;
        }

        if ($validator->errors()->has('purchase_order_id') || $validator->errors()->has('amount')) {
            return;
        }

        $maximum = $branch['due'];

        if ($this->filled('purchase_order_id')) {
            $order = $branch['orders']->firstWhere('id', (int) $this->input('purchase_order_id'));

            if ($order === null) {
                $validator->errors()->add('purchase_order_id', 'The selected purchase invoice does not belong to the selected branch.');

                return;
            }

            if ($order->payment_due <= 0) {
                $validator->errors()->add('purchase_order_id', 'The selected purchase invoice has no outstanding due.');

                return;
            }

            $maximum = (float) $order->payment_due;
        }

        if ($this->filled('amount') && round((float) $this->input('amount'), 2) > $maximum) {
            $validator->errors()->add('amount', 'The amount may not exceed the outstanding due of '.Money::format($maximum).'.');
        }
    }
}
