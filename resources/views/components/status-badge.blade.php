@props(['status', 'label' => null])

@php
$classes = match($status) {
    'active', 'posted', 'paid', 'delivered', 'completed', 'received', 'approved' => 'badge-success',
    'draft', 'pending' => 'badge-neutral',
    'confirmed', 'partially_paid', 'partial', 'in-transit', 'ordered', 'awaiting-approval' => 'badge-info',
    'cancelled', 'voided', 'inactive', 'rejected' => 'badge-danger',
    'returned', 'reversed', 'due' => 'badge-warning',
    default => 'badge-neutral',
};
$label = $label ?? ucfirst(str_replace('_', ' ', $status));
@endphp

<span class="badge {{ $classes }}">{{ $label }}</span>
