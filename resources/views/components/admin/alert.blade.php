{{--
    TailAdmin's alert. Classes and icon paths verbatim.

    Replaces the hand-rolled flash-message divs that were pasted into four admin pages
    with slightly different colours each time. Every screen's "saved" / "cannot delete"
    message comes through here now.
--}}
@props([
    'variant' => 'info',
    'title' => '',
])

@php
    $styles = [
        'success' => ['border-green-500 bg-green-50 dark:border-green-500/30 dark:bg-green-500/15', 'text-green-500'],
        'error' => ['border-red-500 bg-red-50 dark:border-red-500/30 dark:bg-red-500/15', 'text-red-500'],
        'warning' => ['border-yellow-500 bg-yellow-50 dark:border-yellow-500/30 dark:bg-yellow-500/15', 'text-yellow-500'],
        'info' => ['border-blue-500 bg-blue-50 dark:border-blue-500/30 dark:bg-blue-500/15', 'text-blue-500'],
    ];

    // A filled circle for success, an exclamation circle for everything else — both from
    // TailAdmin's own icon set.
    $paths = [
        'success' => 'M3.70186 12.0001C3.70186 7.41711 7.41711 3.70186 12.0001 3.70186C16.5831 3.70186 20.2984 7.41711 20.2984 12.0001C20.2984 16.5831 16.5831 20.2984 12.0001 20.2984C7.41711 20.2984 3.70186 16.5831 3.70186 12.0001ZM12.0001 1.90186C6.423 1.90186 1.90186 6.423 1.90186 12.0001C1.90186 17.5772 6.423 22.0984 12.0001 22.0984C17.5772 22.0984 22.0984 17.5772 22.0984 12.0001C22.0984 6.423 17.5772 1.90186 12.0001 1.90186ZM15.6197 10.7395C15.9712 10.388 15.9712 9.81819 15.6197 9.46672C15.2683 9.11525 14.6984 9.11525 14.347 9.46672L11.1894 12.6243L9.6533 11.0883C9.30183 10.7368 8.73198 10.7368 8.38051 11.0883C8.02904 11.4397 8.02904 12.0096 8.38051 12.3611L10.553 14.5335C10.7217 14.7023 10.9507 14.7971 11.1894 14.7971C11.428 14.7971 11.657 14.7023 11.8257 14.5335L15.6197 10.7395Z',
        'other' => 'M3.6501 12.0001C3.6501 7.38852 7.38852 3.6501 12.0001 3.6501C16.6117 3.6501 20.3501 7.38852 20.3501 12.0001C20.3501 16.6117 16.6117 20.3501 12.0001 20.3501C7.38852 20.3501 3.6501 16.6117 3.6501 12.0001ZM12.0001 1.8501C6.39441 1.8501 1.8501 6.39441 1.8501 12.0001C1.8501 17.6058 6.39441 22.1501 12.0001 22.1501C17.6058 22.1501 22.1501 17.6058 22.1501 12.0001C22.1501 6.39441 17.6058 1.8501 12.0001 1.8501ZM10.9992 7.52517C10.9992 8.07746 11.4469 8.52517 11.9992 8.52517H12.0002C12.5525 8.52517 13.0002 8.07746 13.0002 7.52517C13.0002 6.97289 12.5525 6.52517 12.0002 6.52517H11.9992C11.4469 6.52517 10.9992 6.97289 10.9992 7.52517ZM12.0002 17.3715C11.586 17.3715 11.2502 17.0357 11.2502 16.6215V10.945C11.2502 10.5308 11.586 10.195 12.0002 10.195C12.4144 10.195 12.7502 10.5308 12.7502 10.945V16.6215C12.7502 17.0357 12.4144 17.3715 12.0002 17.3715Z',
    ];

    [$container, $iconColour] = $styles[$variant] ?? $styles['info'];
    $path = $variant === 'success' ? $paths['success'] : $paths['other'];
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border p-4 {$container}"]) }}>
    <div class="flex items-start gap-3">
        <div class="-mt-0.5 {{ $iconColour }}">
            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="{{ $path }}" fill="" />
            </svg>
        </div>

        <div class="flex-1">
            @if ($title !== '')
                <h4 class="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h4>
            @endif

            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $slot }}</div>
        </div>
    </div>
</div>
