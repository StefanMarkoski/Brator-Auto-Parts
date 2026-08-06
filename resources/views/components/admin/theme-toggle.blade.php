{{--
    Light/dark toggle.

    Two things were wrong with the port of this. It kept its OWN copy of the theme in
    x-data, so it fought the Alpine theme store in the layout — one of them added `dark`
    to <html>, the other also managed the body classes, and clicking the button updated
    only half the page. It now drives the store, which is the single owner of the theme.

    And TailAdmin's own file ends both icons with an "(rest of icon path here)" comment, so
    upstream ships one path segment of each: the moon rendered as a stray sliver. These are
    complete icons drawn to the same 20x20 box.
--}}
<button type="button" x-on:click="$store.theme.toggle()" aria-label="Toggle dark mode"
    class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">

    {{-- Shown while dark: click to go back to light, so this is the sun. --}}
    <svg x-show="$store.theme.theme === 'dark'" x-cloak xmlns="http://www.w3.org/2000/svg" width="20" height="20"
        viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
        <circle cx="10" cy="10" r="3.75" />
        <path d="M10 1.5v2M10 16.5v2M1.5 10h2M16.5 10h2M4 4l1.4 1.4M14.6 14.6L16 16M16 4l-1.4 1.4M5.4 14.6L4 16" />
    </svg>

    {{-- Shown while light: click to go dark, so this is the moon. --}}
    <svg x-show="$store.theme.theme === 'light'" x-cloak xmlns="http://www.w3.org/2000/svg" width="20" height="20"
        viewBox="0 0 20 20" fill="currentColor">
        <path
            d="M17.293 12.474a.75.75 0 0 0-.917-.986 6.25 6.25 0 0 1-7.864-7.864.75.75 0 0 0-.986-.917 8.25 8.25 0 1 0 9.767 9.767Z" />
    </svg>
</button>
