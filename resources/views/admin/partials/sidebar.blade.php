{{--
    Admin sidebar. TailAdmin's classes and Alpine sidebar store, with Brator's menu
    instead of TailAdmin's demo one (which advertised its own component gallery).
--}}
@php
    $nav = [
        ['label' => 'Overview', 'items' => [
            ['name' => 'Dashboard', 'route' => 'admin.dashboard'],
            ['name' => 'Receipts', 'route' => 'admin.receipts.index'],
        ]],
        ['label' => 'Catalogue', 'items' => [
            ['name' => 'Products', 'route' => 'admin.products.index'],
            ['name' => 'Product photos', 'route' => 'admin.product-photos.index'],
            ['name' => 'Categories', 'route' => 'admin.categories.index'],
            ['name' => 'Brands', 'route' => 'admin.brands.index'],
        ]],
        ['label' => 'Storefront', 'items' => [
            ['name' => 'Homepage', 'route' => 'admin.homepage.index'],
            ['name' => 'Coupons', 'route' => 'admin.coupons.index'],
        ]],
        ['label' => 'Supply', 'items' => [
            ['name' => 'Imports', 'route' => 'admin.imports.index'],
        ]],
    ];
@endphp

<aside id="sidebar"
    class="fixed flex flex-col mt-0 top-0 px-5 left-0 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out z-99999 border-r border-gray-200"
    :class="{
        'w-[290px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen || $store.sidebar.isHovered,
        'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }"
    x-on:mouseenter="$store.sidebar.setHovered(true)"
    x-on:mouseleave="$store.sidebar.setHovered(false)">

    <div class="py-8 flex items-center gap-3"
        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered) ? 'justify-center' : 'justify-start'">
        <a href="{{ route('admin.dashboard', [], false) }}" class="flex items-center gap-3">
            <span class="grid place-items-center w-9 h-9 rounded-lg bg-brand-500 text-white font-bold shrink-0">B</span>
            <span class="text-lg font-semibold dark:text-white"
                x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">Brator</span>
        </a>
    </div>

    <div class="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav class="mb-6">
            @foreach ($nav as $group)
                <div class="mb-6">
                    <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400"
                        :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered) ? 'xl:text-center' : 'justify-start'">
                        <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">{{ $group['label'] }}</span>
                        <span x-show="!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen">&middot;&middot;&middot;</span>
                    </h3>
                    <ul class="flex flex-col gap-1">
                        @foreach ($group['items'] as $item)
                            @php($active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'))
                            <li>
                                <a href="{{ route($item['route'], [], false) }}"
                                    class="relative flex items-center w-full gap-3 px-3 py-2 font-medium rounded-lg text-sm {{ $active ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.03]' }}"
                                    :class="(!$store.sidebar.isExpanded && !$store.sidebar.isHovered && !$store.sidebar.isMobileOpen) ? 'xl:justify-center' : ''">
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $active ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                                    <span x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">{{ $item['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="mb-8 rounded-2xl bg-gray-50 px-4 py-4 text-center dark:bg-white/[0.03]"
            x-show="$store.sidebar.isExpanded || $store.sidebar.isHovered || $store.sidebar.isMobileOpen">
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Storefront runs on the purchased Brator theme and is styled separately from this panel.</p>
            <a href="{{ route('home', [], false) }}" target="_blank"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-xs font-medium text-white hover:bg-brand-600">View shop</a>
        </div>
    </div>
</aside>
