{{--
    Single consolidated status badge for a purchase invoice.

    The manager-facing state machine (PurchaseOrder::managerDisplayStatus())
    is rendered here: "Pending" while a placed order still awaits the
    supplier's acceptance, "Ordered" once accepted, "Received" once the goods
    are received and "Cancelled" when reversed. The approval
    branches remain only for legacy rows created before purchases took effect
    at creation: "Rejected" for a refused approval and "Awaiting Approval"
    while a legacy sign-off is still pending.
--}}
@php
    [$status, $label] = $order->managerDisplayStatus();
@endphp
<x-status-badge :status="$status" :label="$label" />
