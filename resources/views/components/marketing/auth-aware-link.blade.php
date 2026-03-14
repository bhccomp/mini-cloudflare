@props([
    'guestLabel',
    'guestHref' => url('/register'),
    'authLabel' => 'Dashboard',
    'class' => '',
])

@php
    $user = auth()->user();
    $href = $user
        ? ($user->is_super_admin ? url('/admin') : url('/app'))
        : $guestHref;
    $label = $user ? $authLabel : $guestLabel;
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $label }}</a>
