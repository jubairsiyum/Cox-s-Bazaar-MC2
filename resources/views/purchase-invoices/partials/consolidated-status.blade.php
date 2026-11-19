{{--
    Single consolidated status badge for a purchase invoice.

    The approval dimension and the purchase dimension are merged so exactly one
    badge renders at a time. Purchases take effect on creation, so new documents
    always read as their purchase status ("Received", or "Cancelled" when
    reversed). The approval branches remain only for legacy rows created before
    that lifecycle: "Rejected" for a refused approval and "Awaiting Approval"
    while a legacy sign-off is still pending.
--}}
@php
    [$status, $label] = match (true) {
        $order->approval_status === \App\Enums\ApprovalStatus::Rejected => ['rejected', \App\Enums\ApprovalStatus::Rejected->label()],
        $order->purchase_status === \App\Enums\PurchaseStatus::Cancelled => ['cancelled', \App\Enums\PurchaseStatus::Cancelled->label()],
        $order->approval_status === \App\Enums\ApprovalStatus::Pending => ['pending', \App\Enums\ApprovalStatus::Pending->label()],
        $order->purchase_status === \App\Enums\PurchaseStatus::Received => ['received', \App\Enums\PurchaseStatus::Received->label()],
        default => [$order->purchase_status->value, $order->purchase_status->label()],
    };
@endphp
<x-status-badge :status="$status" :label="$label" />
