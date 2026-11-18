<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesReturn;

use App\Models\SalesReturn;
use Illuminate\Support\Collection;

/**
 * Editing a return revalidates it against the invoice like a fresh
 * submission; the editable return's own quantities are excluded from the
 * already-returned math by overriding returnableQuantities() downstream.
 */
class UpdateSalesReturnRequest extends StoreSalesReturnRequest
{
    public function authorize(): bool
    {
        $return = $this->route('salesReturn');

        return $return instanceof SalesReturn
            && ($this->user()?->can('update', $return) ?? false);
    }

    /**
     * @return Collection<int, int>
     */
    protected function returnableQuantities(): Collection
    {
        $quantities = parent::returnableQuantities();
        $return = $this->route('salesReturn');

        if ($return instanceof SalesReturn) {
            foreach ($return->items as $item) {
                $quantities[(int) $item->unit_id] = (int) ($quantities[(int) $item->unit_id] ?? 0) + (int) $item->quantity;
            }
        }

        return $quantities;
    }
}
