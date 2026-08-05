{{-- Admin top bar: sidebar toggle, page title slot, theme toggle, sign out. --}}
<header class="sticky top-0 flex w-full bg-white border-gray-200 z-99999 dark:border-gray-800 dark:bg-gray-900 lg:border-b">
    <div class="flex items-center justify-between grow lg:px-6 px-4 py-3">
        <div class="flex items-center gap-3">
            <button
                class="items-center justify-center w-10 h-10 text-gray-500 border-gray-200 rounded-lg z-99999 dark:border-gray-800 lg:flex dark:text-gray-400 border"
                x-on:click.stop="window.innerWidth >= 1280 ? $store.sidebar.toggleExpanded() : $store.sidebar.toggleMobileOpen()"
                aria-label="Toggle sidebar">
                <svg class="mx-auto" width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                        d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                        fill="currentColor" />
                </svg>
            </button>
            <h1 class="text-base font-semibold text-gray-800 dark:text-white/90">@yield('heading', 'Dashboard')</h1>
        </div>

        <div class="flex items-center gap-2">
            <x-admin.theme-toggle />
            <span class="hidden text-sm text-gray-500 dark:text-gray-400 sm:inline">{{ auth()->user()?->name }}</span>
            <form method="post" action="{{ route('admin.logout', [], false) }}">
                @csrf
                <button type="submit"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                    Sign out
                </button>
            </form>
        </div>
    </div>
</header>
