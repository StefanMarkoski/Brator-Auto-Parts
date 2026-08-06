{{--
    TailAdmin's text input. Classes verbatim from their form gallery, plus the error state
    from the same gallery's "input-states" page.
--}}
@props(['name' => null])

@php
    $invalid = $name !== null && $errors->has($name);

    $classes = 'dark:bg-dark-900 shadow-theme-xs h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm '
        .'text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:bg-gray-900 '
        .'dark:text-white/90 dark:placeholder:text-white/30 '
        .($invalid
            ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
            : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800');
@endphp

<input {{ $attributes->merge([
    'class' => $classes,
    'type' => $attributes->get('type', 'text'),
    'name' => $name,
    'id' => $attributes->get('id', $name),
]) }} />
