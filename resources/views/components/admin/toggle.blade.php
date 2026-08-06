{{--
    TailAdmin's toggle switch. Their markup keeps a real checkbox and hides it (sr-only),
    so keyboard and screen readers still work and the value posts normally.

    The hidden "0" in front is not decoration. An unchecked checkbox posts NOTHING, so a
    form that only sends `is_active=1` when ticked cannot express "turn this off" — the
    controller sees an absent key and has to guess. This pair always posts exactly one
    value, which is why the state can be turned off as well as on.
--}}
@props([
    'name',
    'checked' => false,
    'label' => null,
    'value' => '1',
])

@php
    $id = $attributes->get('id', $name);
    $isOn = (bool) old($name, $checked);
@endphp

<div x-data="{ on: @js($isOn) }" class="inline-block">
    <input type="hidden" name="{{ $name }}" value="0" />

    <label for="{{ $id }}"
        class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 select-none dark:text-gray-400">
        <div class="relative">
            <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
                class="sr-only" @checked($isOn) x-on:change="on = $event.target.checked"
                {{ $attributes->except(['id', 'class']) }} />
            <div class="block h-6 w-11 rounded-full"
                :class="on ? 'bg-brand-500 dark:bg-brand-500' : 'bg-gray-200 dark:bg-white/10'"></div>
            <div :class="on ? 'translate-x-full' : 'translate-x-0'"
                class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"></div>
        </div>

        @if ($label !== null)
            {{ $label }}
        @endif
    </label>
</div>
