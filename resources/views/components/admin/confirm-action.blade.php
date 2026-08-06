{{--
    A destructive action behind TailAdmin's modal: trigger button, backdrop, confirmation
    copy, and the form that actually performs it.

    Self-contained on purpose. The alternative — a modal somewhere on the page and a
    trigger elsewhere flipping a shared Alpine flag — needs unique ids per row and breaks
    quietly the moment two rows collide. Here each instance owns its own `open`.

    The form is real: method spoofing and CSRF included, so the action still completes if
    Alpine never boots (the modal simply will not have hidden itself first). That matters
    more than it sounds — every dead control on the storefront came from markup that
    assumed JavaScript it did not have.
--}}
@props([
    'action',
    'method' => 'DELETE',
    'label' => 'Delete',
    'title' => 'Are you sure?',
    'message' => 'This cannot be undone.',
    'confirm' => 'Yes, delete',
    'variant' => 'danger',
    'triggerClass' => null,
    'disabled' => false,
    'disabledReason' => null,
])

<div x-data="{ open: false }" class="inline-block">
    @if ($disabled)
        <span class="cursor-not-allowed text-sm font-medium text-gray-400"
            @if ($disabledReason) title="{{ $disabledReason }}" @endif>{{ $label }}</span>
    @elseif ($triggerClass !== null)
        <button type="button" x-on:click="open = true" class="{{ $triggerClass }}">{{ $label }}</button>
    @else
        <x-admin.button :variant="$variant" size="sm" x-on:click="open = true">{{ $label }}</x-admin.button>
    @endif

    <div x-show="open" x-cloak x-on:keydown.escape.window="open = false"
        class="fixed inset-0 z-99999 flex items-center justify-center overflow-y-auto p-5">

        <div x-on:click="open = false" class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        <div x-on:click.stop class="relative w-full max-w-md rounded-3xl bg-white p-6 dark:bg-gray-900 sm:p-8"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95">

            <h4 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h4>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">{{ $message }}</p>

            <form method="post" action="{{ $action }}" class="flex justify-end gap-3">
                @csrf
                @method($method)

                {{-- Extra hidden fields, when the action needs more than an id. --}}
                {{ $slot }}

                <x-admin.button variant="outline" size="sm" x-on:click="open = false">Cancel</x-admin.button>
                <x-admin.button type="submit" :variant="$variant" size="sm">{{ $confirm }}</x-admin.button>
            </form>
        </div>
    </div>
</div>
