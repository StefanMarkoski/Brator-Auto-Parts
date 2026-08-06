{{--
    The label / control / hint / error wrapper every admin input sits in.

    Validation errors are shown HERE, once, rather than being remembered per field on each
    page. The product editor previously rendered `old()` values but never displayed a
    single error message, so a rejected save looked like a save that silently did nothing.
--}}
@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'required' => false,
    /* Shown as a small "yours" pill — the manual-override marker on product fields. */
    'owned' => false,
])

@php
    $error = $name !== null ? ($errors->first($name) ?: null) : null;
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label !== null)
        <label @if ($name) for="{{ $name }}" @endif
            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $label }}
            @if ($required)
                <span class="text-error-500">*</span>
            @endif
            @if ($owned)
                <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">yours</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($error !== null)
        <p class="mt-1.5 text-xs text-error-500">{{ $error }}</p>
    @elseif ($hint !== null)
        <p class="mt-1.5 text-xs text-gray-400">{{ $hint }}</p>
    @endif
</div>
