@extends('admin.layouts.admin')
@section('title', 'Homepage')
@section('heading', 'Homepage')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Homepage" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was changed" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    <x-admin.alert variant="info" title="What this screen can and cannot do" class="mb-6">
        Reorder the strips, hide them, change their headings, and choose which collection
        feeds a product strip. You cannot add a new <em>kind</em> of section: each one renders
        through a piece of the purchased theme's own markup, and a kind the theme has no
        markup for would mean changing the design.
    </x-admin.alert>

    <div class="space-y-4">
        @foreach ($sections as $section)
            @php
                $takesCollection = in_array($section->section_type, $collectionBacked, true);
                $label = ucwords(str_replace('_', ' ', $section->section_type));
            @endphp

            <x-admin.component-card :title="$label"
                :desc="$section->heading ? 'Heading: '.$section->heading : 'This section has no heading of its own.'">

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <x-admin.badge size="sm" :color="$section->is_visible ? 'success' : 'light'">
                            {{ $section->is_visible ? 'Shown' : 'Hidden' }}
                        </x-admin.badge>
                        <span class="text-xs text-gray-400">position {{ $section->position + 1 }} of {{ $sections->count() }}</span>
                    </div>

                    {{-- Disabled at the ends rather than left clickable and inert: the top
                         section's "up" doing nothing is indistinguishable from a broken
                         button. --}}
                    <div class="flex items-center gap-2">
                        <form method="post" action="{{ route('admin.homepage.move', $section->id, false) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="direction" value="up" />
                            <x-admin.button type="submit" variant="outline" size="sm"
                                :disabled="$loop->first">Move up</x-admin.button>
                        </form>

                        <form method="post" action="{{ route('admin.homepage.move', $section->id, false) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="direction" value="down" />
                            <x-admin.button type="submit" variant="outline" size="sm"
                                :disabled="$loop->last">Move down</x-admin.button>
                        </form>
                    </div>
                </div>

                <form method="post" action="{{ route('admin.homepage.update', $section->id, false) }}"
                    class="space-y-5 border-t border-gray-100 pt-5 dark:border-gray-800">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-admin.field label="Heading" :name="'heading-'.$section->id"
                            hint="Leave blank to render the section with no heading.">
                            <x-admin.input name="heading" :value="old('heading', $section->heading)" />
                        </x-admin.field>

                        <x-admin.field label="Subheading" :name="'subheading-'.$section->id">
                            <x-admin.input name="subheading" :value="old('subheading', $section->subheading)" />
                        </x-admin.field>
                    </div>

                    @if ($takesCollection)
                        <x-admin.field label="Products shown" :name="'collection-'.$section->id"
                            hint="Which collection fills this strip.">
                            <x-admin.select name="product_collection_id"
                                :options="$collections->pluck('name', 'id')->all()"
                                :selected="$section->product_collection_id"
                                placeholder="— none, the strip will be empty" />
                        </x-admin.field>
                    @else
                        <p class="text-xs text-gray-400">
                            This section does not render a product strip, so it has no collection
                            to choose. It draws its own content —
                            {{ match ($section->section_type) {
                                'hero_banner' => 'the banners marked for the homepage hero',
                                'categories_strip' => 'the top-level departments',
                                'whats_hot' => 'the promo boxes marked home_secondary',
                                'featured_makes' => 'the vehicle makes',
                                'articles' => 'the published articles',
                                'featured_brands' => 'the active brands',
                                'newsletter' => 'a fixed sign-up form',
                                default => 'its own source',
                            } }}.
                        </p>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <x-admin.toggle :name="'is_visible'" label="Show on the homepage"
                            :id="'visible-'.$section->id" :checked="$section->is_visible" />

                        <x-admin.button type="submit" size="sm">Save section</x-admin.button>
                    </div>
                </form>

                @if ($section->section_type === 'hero_banner')
                    {{--
                        The hero's pictures live inside the hero section's own card rather than in
                        a card of their own, because that is where somebody looks for them.
                    --}}
                    <div class="mt-6 space-y-4 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <div>
                            <h4 class="text-sm font-medium text-gray-800 dark:text-white/90">Pictures</h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if ($heroImages->count() > 1)
                                    {{ $heroImages->count() }} pictures, so the banner rotates every
                                    5 seconds and shows a row of dots you can click to jump.
                                @elseif ($heroImages->count() === 1)
                                    One picture, so it sits still and there are no dots. Add another
                                    and the banner starts rotating on its own.
                                @else
                                    None yet, so the banner falls back to the theme's own background.
                                @endif
                            </p>
                        </div>

                        @if ($heroImages->isNotEmpty())
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($heroImages as $index => $image)
                                    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
                                        {{-- object-cover, the same way the storefront paints it, so
                                             this preview shows the crop the shopper will see rather
                                             than a squashed whole picture. --}}
                                        <img src="/{{ $image->image_path }}" alt=""
                                            class="h-28 w-full bg-gray-100 object-cover dark:bg-gray-900" />

                                        <div class="space-y-2 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs text-gray-400">#{{ $index + 1 }} in the rotation</span>
                                                <span class="text-xs {{ $image->isComfortableForHero() ? 'text-gray-500 dark:text-gray-400' : 'text-warning-600 dark:text-warning-400' }}">
                                                    {{ $image->dimensions() ?? 'theme asset' }}
                                                </span>
                                            </div>

                                            @unless ($image->isComfortableForHero())
                                                <p class="text-xs text-warning-600 dark:text-warning-400">
                                                    Narrower than {{ $comfortableWidth }}px, so it is
                                                    enlarged to fill the banner and will look soft.
                                                </p>
                                            @endunless

                                            @if ($image->source_url)
                                                <p class="truncate text-xs text-gray-400" title="{{ $image->source_url }}">
                                                    from {{ parse_url($image->source_url, PHP_URL_HOST) }}
                                                </p>
                                            @endif

                                            <x-admin.confirm-action
                                                :action="route('admin.homepage.hero-images.destroy', $image->id, false)"
                                                method="DELETE"
                                                label="Remove"
                                                trigger-class="text-xs font-medium text-error-600 hover:underline dark:text-error-400"
                                                title="Remove this picture?"
                                                message="It comes off the banner straight away. The file is deleted too, so you would need the URL again to put it back."
                                                confirm="Yes, remove it" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Its own form, a sibling of the section form above — never nested. A
                             form inside a form posts to the outer one. --}}
                        <form method="post" action="{{ route('admin.homepage.hero-images.store', [], false) }}"
                            class="flex flex-wrap items-end gap-3">
                            @csrf

                            <div class="min-w-[16rem] flex-1">
                                <x-admin.field label="Add a picture by URL" name="url"
                                    :hint="'The file is downloaded and served from this shop, not linked to. '
                                        .$comfortableWidth.'px wide or more looks best. JPEG, PNG, WebP, GIF or AVIF.'">
                                    <x-admin.input name="url" type="url" :value="old('url')"
                                        placeholder="https://example.com/car.webp" required />
                                </x-admin.field>
                            </div>

                            <x-admin.button type="submit" size="sm">Add picture</x-admin.button>
                        </form>
                    </div>
                @endif
            </x-admin.component-card>
        @endforeach
    </div>

    <div class="mt-6">
        <x-admin.button variant="outline" :href="route('home', [], false)" target="_blank">
            Open the homepage
        </x-admin.button>
    </div>
@endsection
