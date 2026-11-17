@props([
    'name' => '',
    'size' => 'default', // 'xs', 'sm', 'default', 'lg', 'xl'
    'class' => '',
    'showContainer' => true,
    'containerClass' => '',
])

@php
    $normalized = strtolower(trim((string) $name));
    $key = match (true) {
        str_contains($normalized, 'samsung') => 'samsung',
        str_contains($normalized, 'apple') || str_contains($normalized, 'iphone') => 'apple',
        str_contains($normalized, 'redmi') => 'redmi',
        str_contains($normalized, 'xiaomi') || str_contains($normalized, 'mi ') || $normalized === 'mi' => 'xiaomi',
        str_contains($normalized, 'realme') => 'realme',
        str_contains($normalized, 'oneplus') || str_contains($normalized, 'one plus') => 'oneplus',
        str_contains($normalized, 'oppo') => 'oppo',
        str_contains($normalized, 'vivo') => 'vivo',
        str_contains($normalized, 'nokia') => 'nokia',
        str_contains($normalized, 'moto') => 'motorola',
        str_contains($normalized, 'google') || str_contains($normalized, 'pixel') => 'google',
        str_contains($normalized, 'huawei') => 'huawei',
        str_contains($normalized, 'honor') => 'honor',
        str_contains($normalized, 'infinix') => 'infinix',
        str_contains($normalized, 'tecno') => 'tecno',
        str_contains($normalized, 'itel') => 'itel',
        str_contains($normalized, 'symphony') => 'symphony',
        str_contains($normalized, 'walton') => 'walton',
        str_contains($normalized, 'sony') => 'sony',
        str_contains($normalized, 'asus') => 'asus',
        str_contains($normalized, 'nothing') => 'nothing',
        str_contains($normalized, 'poco') => 'poco',
        str_contains($normalized, 'lenovo') => 'lenovo',
        str_contains($normalized, 'zte') => 'zte',
        default => \Illuminate\Support\Str::slug($normalized),
    };

    $logoPath = "images/brands/{$key}.svg";
    $hasLogo = file_exists(public_path($logoPath));
    $initials = strtoupper(mb_substr($name ?: 'BR', 0, 2));

    // Tailwind sizing maps
    $imgSizeClass = match ($size) {
        'xs' => 'max-h-3.5 max-w-3.5',
        'sm' => 'max-h-5 max-w-5',
        'lg' => 'max-h-8 max-w-8',
        'xl' => 'max-h-10 max-w-10',
        default => 'max-h-6 max-w-6',
    };

    $boxSizeClass = match ($size) {
        'xs' => 'size-6 rounded-md p-0.5',
        'sm' => 'size-8 rounded-lg p-1',
        'lg' => 'size-12 rounded-2xl p-2.5',
        'xl' => 'size-14 rounded-2xl p-3',
        default => 'size-10 rounded-xl p-2',
    };

    // Pixel fallbacks for standalone/print contexts without Tailwind
    $maxPx = match ($size) {
        'xs' => 16,
        'sm' => 20,
        'lg' => 32,
        'xl' => 40,
        default => 24,
    };

    $boxPx = match ($size) {
        'xs' => 24,
        'sm' => 32,
        'lg' => 48,
        'xl' => 56,
        default => 40,
    };

    $boxStyle = "width: {$boxPx}px; height: {$boxPx}px; min-width: {$boxPx}px; display: inline-flex; align-items: center; justify-content: center;";
    $imgStyle = "max-width: {$maxPx}px; max-height: {$maxPx}px; width: auto; height: auto; object-fit: contain;";

    // Logos that are pure black and need light inversion in dark mode
    $invertInDark = in_array($key, ['apple', 'honor', 'sony', 'nothing'], true);
    $darkInvertClass = $invertInDark ? 'dark:invert dark:brightness-200' : '';
@endphp

@if ($showContainer)
    <div {{ $attributes->merge([
        'class' => "{$boxSizeClass} flex shrink-0 items-center justify-center bg-surface border border-line shadow-2xs transition-transform duration-150 " . ($containerClass ?: ''),
        'style' => $boxStyle
    ]) }}>
        @if ($hasLogo)
            <img src="{{ asset($logoPath) }}"
                 alt="{{ $name }}"
                 loading="lazy"
                 class="{{ $imgSizeClass }} w-auto h-auto object-contain {{ $darkInvertClass }} {{ $class }}"
                 style="{{ $imgStyle }} display: block;">
        @else
            <span class="font-bold text-[11px] text-brand-500 uppercase tracking-wider select-none">
                {{ $initials }}
            </span>
        @endif
    </div>
@else
    @if ($hasLogo)
        <img src="{{ asset($logoPath) }}"
             alt="{{ $name }}"
             loading="lazy"
             {{ $attributes->merge([
                 'class' => "{$imgSizeClass} w-auto h-auto object-contain {$darkInvertClass} {$class}",
                 'style' => "{$imgStyle} display: inline-block;"
             ]) }}>
    @else
        <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center font-bold text-xs text-brand-500 uppercase']) }}>
            {{ $initials }}
        </span>
    @endif
@endif
