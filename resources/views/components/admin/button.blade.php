{{--
    TailAdmin's button, as a real component.

    Upstream ships this markup inside a demo gallery page, and its component version
    carries two bits of cruft we do not want: a no-op @if line, and @hasSection checks,
    which only work in a layout and never fire inside a component. The classes below are
    theirs verbatim; the API is ours.

    Renders an <a> when given an href, so a link that looks like a button is still a link
    — middle-click and "open in new tab" keep working, which they do not on a <button>
    wrapped in a form.
--}}
@props([
    'size' => 'md',
    'variant' => 'primary',
    'href' => null,
    'disabled' => false,
])

@php
    $sizes = [
        'sm' => 'px-4 py-3 text-sm',
        'md' => 'px-5 py-3.5 text-sm',
    ];

    $variants = [
        'primary' => 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300',
        'outline' => 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300',
        'danger' => 'bg-error-500 text-white shadow-theme-xs hover:bg-error-600 disabled:bg-error-300',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center font-medium gap-2 rounded-lg transition',
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
        $disabled ? 'cursor-not-allowed opacity-50' : '',
    ]);
@endphp

@if ($href !== null && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => $attributes->get('type', 'button')]) }}
        @disabled($disabled)>{{ $slot }}</button>
@endif
