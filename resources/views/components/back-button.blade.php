@props([
    'route' => null,
    'routeParam' => null,
    'label' => 'Back',
    'icon' => 'fa-arrow-left',
    'variant' => 'secondary',
])

@php
    $backUrl = \App\Support\BackNavigation::resolve($route, $routeParam);
    $variantClass = match ($variant) {
        'primary' => 'back-button-primary',
        default => 'back-button-secondary',
    };
@endphp

<link rel="prefetch" href="{{ $backUrl }}">

<a
    href="{{ $backUrl }}"
    class="back-button {{ $variantClass }}"
    data-ee-instant-back
    aria-label="{{ $label }}"
>
    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</a>
