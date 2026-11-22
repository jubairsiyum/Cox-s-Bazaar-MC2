@props(['status', 'label' => null])

@php
$statusValue = $status instanceof \BackedEnum
    ? $status->value
    : ($status instanceof \UnitEnum ? $status->name : (string) $status);

$classes = match(strtolower((string) $statusValue)) {
    'active', 'posted', 'paid', 'delivered', 'completed', 'received', 'approved' => 'badge-success',
    'draft', 'pending' => 'badge-neutral',
    'confirmed', 'partially_paid', 'partial', 'in-transit', 'ordered', 'awaiting-approval', 'requested' => 'badge-info',
    'cancelled', 'voided', 'inactive', 'rejected' => 'badge-danger',
    'returned', 'reversed', 'due' => 'badge-warning',
    default => 'badge-neutral',
};

if ($label === null) {
    if ($status instanceof \BackedEnum && method_exists($status, 'label')) {
        $label = $status->label();
    } else {
        $label = ucfirst(str_replace(['_', '-'], ' ', (string) $statusValue));
    }
}
@endphp

<span class="badge {{ $classes }}">{{ $label }}</span>
