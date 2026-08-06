{{--
    TailAdmin's select: the native element with its arrow suppressed and their own chevron
    positioned over it, so it looks designed while still being a real <select>.

    Nothing like select2 here. The storefront's dropdowns are wrapped by the theme's
    select2, which is why a native change listener never fired on them; the admin keeps
    the plain element so a change event is a change event.

    Options come from `:options` (value => label) rather than a slot, since every admin
    select is a straight map. Pass a slot instead when the options need grouping.
--}}
@props([
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
])

@php
    $invalid = $name !== null && $errors->has($name);
    $current = $name !== null ? old($name, $selected) : $selected;

    $classes = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full appearance-none rounded-lg border bg-transparent '
        .'bg-none px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:bg-gray-900 '
        .'dark:text-white/90 '
        .($invalid
            ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
            : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800');
@endphp

<div class="relative bg-transparent">
    <select {{ $attributes->merge(['class' => $classes, 'name' => $name, 'id' => $attributes->get('id', $name)]) }}>
        @if ($placeholder !== null)
            <option value="" class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected((string) $current === (string) $value)
                class="text-gray-700 dark:bg-gray-900 dark:text-gray-400">{{ $label }}</option>
        @endforeach

        {{ $slot }}
    </select>

    <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
        <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5"
                stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
</div>
