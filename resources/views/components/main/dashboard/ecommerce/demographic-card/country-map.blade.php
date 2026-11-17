@props(['markers' => [], 'mapColor' => 'var(--line)'])
<div data-dashboard-chart="country-map" data-config="{{ json_encode(['markers' => $markers, 'mapColor' => $mapColor]) }}" class="size-full"></div>
