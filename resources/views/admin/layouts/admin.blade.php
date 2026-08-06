{{--
    Admin layout — TailAdmin (Tailwind v4 + Alpine), ported.

    THE ISOLATION RULE: this is the ONLY layout that references @vite. The storefront
    layouts load the purchased theme's CSS/JS straight off disk and no bundler at all.
    The two share nothing — not a partial, not a head, not a class. Tailwind's global
    reset would flatten the Brator theme anywhere the two met, so the separation is
    structural rather than a convention someone has to remember.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') &middot; Brator Admin</title>

    <!-- Scripts -->
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    
    {{--
        The Alpine theme + sidebar stores live in resources/js/admin.js, not here. They are
        JavaScript, not markup, and keeping them in the head buried the one script below
        that genuinely has to run before first paint.
    --}}

    <!-- Apply dark mode immediately to prevent flash -->
    <script>
        (function() {
            /*
             | This runs in <head>, where document.body DOES NOT EXIST YET. TailAdmin's
             | version touches document.body here, so it threw a TypeError on every single
             | admin page load — and because it threw, the lines after it never ran either.
             |
             | Only documentElement is safe this early. The body classes are applied by the
             | Alpine theme store in admin.js, once there is a body to apply them to.
            */
            const saved = localStorage.getItem('theme');
            const system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

            document.documentElement.classList.toggle('dark', (saved || system) === 'dark');
        })();
    </script>
    
</head>

<body
    x-data="{ 'loaded': true}"
    x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = true;
        }
    };
    window.addEventListener('resize', checkMobile);">

    {{-- preloader --}}
    
    {{-- preloader end --}}

    <div class="min-h-screen xl:flex">
        @include('admin.partials.backdrop')
        @include('admin.partials.sidebar')

        <div class="flex-1 transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('admin.partials.header')
            <!-- app header end -->
            <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
                @yield('content')
            </div>
        </div>

    </div>

</body>

@stack('scripts')

</html>
