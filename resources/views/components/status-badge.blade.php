@props(['status'])

@php
$classes = match($status) {
    'active', 'posted', 'paid', 'delivered', 'completed' => 'badge-success',
    'draft', 'pending' => 'badge-neutral',
    'confirmed', 'partially_paid', 'partial' => 'badge-info',
    'cancelled', 'voided', 'inactive' => 'badge-danger',
    'returned', 'reversed' => 'badge-warning',
    default => 'badge-neutral',
};
$label = ucfirst(str_replace('_', ' ', $status));
@endphp

<span class="badge {{ $classes }}">{{ $label }}</span>
