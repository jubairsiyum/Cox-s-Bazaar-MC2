<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierPaymentRequestStatus;
use App\Helpers\Money;
use App\Http\Requests\SupplierProfile\SendPaymentRequestRequest;
use App\Http\Requests\SupplierProfile\UpdateOwnSupplierProfileRequest;
use App\Http\Requests\SupplierProfile\UpdateSupplierPaymentRequestRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPaymentRequest;
use App\Services\AuditService;
use App\Services\BranchScope;
use App\Services\DuplicateCheckService;
use App\Services\ManagerLookup;
use App\Services\NotificationService;
use App\Services\PurchaseOrderWorkflow;
use App\Services\SupplierProfileData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The Supplier Profile: the supplier's "home" inside the admin app.
 *
 * Staff open this page on the supplier's behalf; a supplier login (Supplier
 * role, linked via suppliers.user_id) sees only its own profile. The page
 * carries the supplier's details, summary, branch-wise dues, every purchase
 * order with its lines, the payments and returns against the supplier, the
 * payment-request messaging to branches that owe money, and the activity feed.
 * Section data is built by SupplierProfileData.
 */
class SupplierProfileController extends Controller
{
    /**
     * Land the signed-in supplier on the Details page of their own profile.
     */
    public function ownProfile(Request $request): RedirectResponse
    {
        $this->linkedSupplier($request);

        return redirect()->route('suppliers.my-profile.section', 'details');
    }

    /**
     * Display one section of the signed-in supplier's own profile, with only
     * the data that section renders.
     */
    public function ownSection(Request $request, string $section): View
    {
        abort_unless(array_key_exists($section, SupplierProfileData::SECTIONS), 404);

        $supplier = $this->linkedSupplier($request);

        $this->authorize('view', $supplier);

        return view('suppliers.portal.section', array_merge(
            [
                'supplier' => $supplier,
                'section' => $section,
                'sectionMeta' => SupplierProfileData::SECTIONS[$section],
            ],
            SupplierProfileData::for($supplier, $request->user(), $section),
        ));
    }

    /**
     * The supplier linked to the signed-in login, or 403 when there is none.
     */
    private function linkedSupplier(Request $request): Supplier
    {
        $supplier = Supplier::query()->where('user_id', $request->user()->id)->first();

        abort_if($supplier === null, 403, 'No supplier profile is linked to your account.');

        return $supplier;
    }

    /**
     * Display the supplier profile.
     *
     * Strict branch isolation: the dues, orders, payments, returns, requests
     * and feed entries are scoped by SupplierProfileData. The Super Admin sees
     * all branches; branch users see only their own branch's rows and can only
     * act on those. Restricted users without a branch assignment see none of
     * it. A supplier login sees all of its own supplier's data across branches.
     */
    public function profile(Request $request, Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.profile', array_merge(
            ['supplier' => $supplier],
            SupplierProfileData::for($supplier, $request->user()),
        ));
    }

    /**
     * Record the supplier's acceptance of a purchase order: the branch side
     * dynamically flips from "Pending" to "Ordered".
     */
    public function acceptOrder(Request $request, Supplier $supplier, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('recordOrderStatus', $supplier);

        abort_unless((int) $purchaseOrder->supplier_id === (int) $supplier->id, 404);
        abort_unless($request->user()->isSupplierPortalUser() || BranchScope::canAccess($request->user(), $purchaseOrder->location_id), 403);

        if ($purchaseOrder->purchase_status === PurchaseStatus::Cancelled) {
            return back()->with('error', 'A cancelled purchase order cannot be accepted.');
        }

        if ($purchaseOrder->supplier_status !== SupplierOrderStatus::Pending) {
            return back()->with('error', "Purchase order '{$purchaseOrder->reference_no}' has already been accepted.");
        }

        $original = $purchaseOrder->getOriginal();

        PurchaseOrderWorkflow::recordAcceptance($purchaseOrder, $request->user());

        AuditService::updated(
            $purchaseOrder,
            $original,
            sprintf("Supplier '%s' accepted purchase order '%s' — the branch now sees it as Ordered.", $supplier->name, $purchaseOrder->reference_no)
        );

        return back()->with('success', "Purchase order '{$purchaseOrder->reference_no}' accepted — the branch now sees it as Ordered.");
    }

    /**
     * Send a payment request to a branch on the supplier's behalf: the
     * targeted branch gets an in-app notification linking to the pre-filled
     * supplier payment recording screen. Branch rules (dues, amount limit and
     * branch scope) are checked by SendPaymentRequestRequest.
     */
    public function sendPaymentRequest(SendPaymentRequestRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('requestPayment', $supplier);

        $validated = $request->validated();
        $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : null;

        $paymentRequest = SupplierPaymentRequest::create([
            'supplier_id' => $supplier->id,
            'location_id' => (int) $validated['location_id'],
            'purchase_order_id' => $validated['purchase_order_id'] ?? null,
            'message' => $validated['message'],
            'amount' => $amount,
            'status' => SupplierPaymentRequestStatus::Open,
            'created_by' => $request->user()?->id,
        ]);

        $paymentRequest->load(['location', 'purchaseOrder']);

        AuditService::created(
            $paymentRequest,
            sprintf(
                "Supplier '%s' requested a payment%s from %s.",
                $supplier->name,
                $amount !== null ? ' of '.Money::format($amount) : '',
                $paymentRequest->location?->name ?? 'Unknown branch'
            )
        );

        $this->notifyManagers($supplier, $paymentRequest, "Payment request from {$supplier->name}");

        return back()->with('success', 'Payment request sent to '.$paymentRequest->location?->name.'.');
    }

    /**
     * Edit an open payment request the supplier sent: its message, amount and
     * purchase invoice, with the branch fixed. The branch is told of the change.
     */
    public function updateOwnPaymentRequest(UpdateSupplierPaymentRequestRequest $request, SupplierPaymentRequest $paymentRequest): RedirectResponse
    {
        $supplier = $paymentRequest->supplier;
        $validated = $request->validated();
        $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : null;

        $original = $paymentRequest->getOriginal();

        $paymentRequest->update([
            'purchase_order_id' => $validated['purchase_order_id'] ?? null,
            'message' => $validated['message'],
            'amount' => $amount,
        ]);

        $paymentRequest->load(['location', 'purchaseOrder']);

        AuditService::updated(
            $paymentRequest,
            $original,
            sprintf("Supplier '%s' edited its payment request to %s.", $supplier->name, $paymentRequest->location?->name ?? 'Unknown branch')
        );

        $this->notifyManagers($supplier, $paymentRequest, "Payment request updated by {$supplier->name}");

        return back()->with('success', 'Payment request updated.');
    }

    /**
     * Save the signed-in supplier's own identity, contact and address details.
     * Trashed records holding the same unique details are purged first, as the
     * staff supplier update does, since the unique indexes ignore soft deletes.
     */
    public function updateOwnProfile(UpdateOwnSupplierProfileRequest $request, Supplier $supplier): RedirectResponse
    {
        $original = $supplier->getOriginal();
        $validated = $request->validated();

        $purged = DuplicateCheckService::purgeTrashConflicts(Supplier::class, $validated, $supplier->id);

        foreach ($purged as $record) {
            AuditService::deleted($record, "Deleted supplier '{$record->name}' ({$record->supplier_id}) permanently removed while supplier '{$supplier->name}' updated its own details.");
        }

        $supplier->update($validated);

        AuditService::updated($supplier, $original, "Supplier '{$supplier->name}' ({$supplier->supplier_id}) updated its own details from the supplier portal.");

        return back()->with('success', 'Your supplier details were updated.');
    }

    /**
     * Change the password of the signed-in supplier login. The login email is
     * left untouched: it is separate from the supplier's contact email.
     */
    public function updateOwnPassword(Request $request): RedirectResponse
    {
        $this->linkedSupplier($request);

        $validated = $request->validateWithBag('updateSupplierPassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Notify the branch's managers of a payment request, linking to the
     * pre-filled supplier payment recording screen.
     */
    private function notifyManagers(Supplier $supplier, SupplierPaymentRequest $paymentRequest, string $title): void
    {
        $amount = $paymentRequest->amount !== null ? (float) $paymentRequest->amount : null;

        $url = route('supplier-payments.create', array_filter([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $paymentRequest->purchase_order_id,
            'amount' => $amount,
            'payment_request_id' => $paymentRequest->id,
        ], fn ($value) => $value !== null));

        NotificationService::notifyMany(
            ManagerLookup::managersFor($paymentRequest->location_id),
            $title,
            sprintf(
                '%s requests a payment%s for %s%s: %s',
                $supplier->name,
                $amount !== null ? ' of '.Money::format($amount) : '',
                $paymentRequest->location?->name ?? 'your branch',
                $paymentRequest->purchaseOrder?->reference_no ? ' ('.$paymentRequest->purchaseOrder->reference_no.')' : '',
                $paymentRequest->message
            ),
            $url,
            'warning'
        );
    }

    /**
     * Withdraw an open payment request.
     */
    public function cancelPaymentRequest(Request $request, Supplier $supplier, SupplierPaymentRequest $paymentRequest): RedirectResponse
    {
        $this->authorize('requestPayment', $supplier);

        abort_unless((int) $paymentRequest->supplier_id === (int) $supplier->id, 404);
        abort_unless($request->user()->isSupplierPortalUser() || BranchScope::canAccess($request->user(), $paymentRequest->location_id), 403);

        if ($paymentRequest->status !== SupplierPaymentRequestStatus::Open) {
            return back()->with('error', 'Only open payment requests can be cancelled.');
        }

        $original = $paymentRequest->getOriginal();

        $paymentRequest->update(['status' => SupplierPaymentRequestStatus::Cancelled]);

        AuditService::updated(
            $paymentRequest,
            $original,
            sprintf("Payment request to %s withdrawn from supplier '%s' profile.", $paymentRequest->location?->name ?? 'Unknown branch', $supplier->name)
        );

        return back()->with('success', 'Payment request cancelled.');
    }
}
