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
                $takesHeading = in_array($section->section_type, $headingBacked, true);
                $takesSubheading = in_array($section->section_type, $subheadingBacked, true);
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

                    {{--
                        Only the boxes this section actually prints.

                        Both were shown on every card, and four sections printed neither — so
                        changing the hero's headline saved, went green, and left the homepage
                        exactly as it was. An unoffered field is honest; an offered dead one is a
                        lie the success message backs up.
                    --}}
                    @if ($takesHeading || $takesSubheading)
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            @if ($takesHeading)
                                <x-admin.field label="Heading" :name="'heading-'.$section->id"
                                    hint="Leave blank to fall back to the theme's own wording.">
                                    <x-admin.input name="heading" :value="old('heading', $section->heading)" />
                                </x-admin.field>
                            @endif

                            @if ($takesSubheading)
                                <x-admin.field label="Subheading" :name="'subheading-'.$section->id"
                                    hint="The smaller line above or below the heading. Blank leaves it out.">
                                    <x-admin.input name="subheading" :value="old('subheading', $section->subheading)" />
                                </x-admin.field>
                            @endif
                        </div>
                    @endif

                    @unless ($takesHeading && $takesSubheading)
                        <p class="text-xs text-gray-400">
                            {{ match (true) {
                                $section->section_type === 'articles'
                                    => 'This section renders nothing at all — blog pages are out of scope — so there is no heading or subheading to set. It can still be reordered and hidden.',
                                $section->section_type === 'featured_makes'
                                    => 'This section has one tab title and no second line beneath it, so it takes a heading but no subheading.',
                                default => 'This section does not print both text lines.',
                            } }}
                        </p>
                    @endunless

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
                                        <img src="{{ \App\Support\ImageUrl::for($image->image_path) }}" alt=""
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

                @if ($section->section_type === 'whats_hot')
                    {{--
                        The What's Hot boxes, inside their own section's card, because that is where
                        somebody looks for them.

                        EVERY BOX LINKS TO A CATEGORY, chosen from a list. All four shipped pointing
                        at /shop whatever they said — the "Alloy Wheels" box did not go to wheels —
                        and a free-text URL field is exactly how a homepage ends up advertising a
                        department the shop does not have.
                    --}}
                    <div class="mt-6 space-y-4 border-t border-gray-100 pt-5 dark:border-gray-800">
                        <div>
                            <h4 class="text-sm font-medium text-gray-800 dark:text-white/90">Promo boxes</h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $whatsHot->count() }} in total, {{ $whatsHot->where('is_active', true)->count() }} showing.
                                The carousel fits {{ $whatsHotVisible }} at a time and slides through any beyond that.
                            </p>
                        </div>

                        @forelse ($whatsHot as $box)
                            @php($linked = $linkableCategories->firstWhere('slug', basename((string) $box->link_url)))
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                <form method="post"
                                    action="{{ route('admin.homepage.whats-hot.update', $box->id, false) }}"
                                    class="space-y-4">
                                    @csrf
                                    @method('PUT')

                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ \App\Support\ImageUrl::for($box->image_path) }}" alt=""
                                                class="h-14 w-14 rounded-lg border border-gray-200 object-cover dark:border-gray-800" />
                                            <div>
                                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                                    {{ str_replace("\n", ' ', $box->title) }}
                                                </p>
                                                <p class="text-xs text-gray-400">
                                                    position {{ $box->position + 1 }} of {{ $whatsHot->count() }}
                                                    @if ($linked)
                                                        — links to {{ $linked->name }}
                                                    @else
                                                        {{-- Named plainly: a box whose link no longer matches a live
                                                             category is the one thing this screen exists to prevent. --}}
                                                        — <span class="text-error-600 dark:text-error-400">its link does not match a live category</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <x-admin.badge size="sm" :color="$box->is_active ? 'success' : 'light'">
                                                {{ $box->is_active ? 'Showing' : 'Hidden' }}
                                            </x-admin.badge>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <x-admin.field label="Headline" :name="'headline-'.$box->id"
                                            hint="Line breaks are kept, which is how the theme stacks it over three lines.">
                                            <x-admin.textarea name="headline" rows="2">{{ $box->title }}</x-admin.textarea>
                                        </x-admin.field>

                                        <div class="space-y-4">
                                            <x-admin.field label="Small line above" :name="'tagline-'.$box->id">
                                                <x-admin.input name="tagline" :value="$box->subtitle" />
                                            </x-admin.field>

                                            <x-admin.field label="Links to" :name="'category-'.$box->id">
                                                <x-admin.select name="category_id"
                                                    :options="$linkableCategories->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', $c->depth).$c->name])->all()"
                                                    :selected="$linked?->id"
                                                    placeholder="— keep the current link" />
                                            </x-admin.field>
                                        </div>
                                    </div>

                                    <x-admin.field label="Replace the background image" :name="'image-'.$box->id"
                                        hint="Leave blank to keep the current one. Downloaded and served from this shop, never linked to.">
                                        <x-admin.input name="image_url" type="url" placeholder="https://example.com/promo.jpg" />
                                    </x-admin.field>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-admin.button type="submit" size="sm">Save box</x-admin.button>
                                    </div>
                                </form>

                                {{-- Each control its own form, all siblings — never nested, which the
                                     browser silently drops. --}}
                                <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                                    <form method="post" action="{{ route('admin.homepage.whats-hot.update', $box->id, false) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $box->is_active ? 0 : 1 }}" />
                                        <button type="submit" class="text-xs font-medium text-brand-500 hover:underline">
                                            {{ $box->is_active ? 'Hide it' : 'Show it' }}
                                        </button>
                                    </form>

                                    <form method="post" action="{{ route('admin.homepage.whats-hot.update', $box->id, false) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="direction" value="up" />
                                        <button type="submit" class="text-xs font-medium text-gray-500 hover:underline disabled:opacity-40 dark:text-gray-400"
                                            @disabled($loop->first)>Move up</button>
                                    </form>

                                    <form method="post" action="{{ route('admin.homepage.whats-hot.update', $box->id, false) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="direction" value="down" />
                                        <button type="submit" class="text-xs font-medium text-gray-500 hover:underline disabled:opacity-40 dark:text-gray-400"
                                            @disabled($loop->last)>Move down</button>
                                    </form>

                                    <x-admin.confirm-action
                                        :action="route('admin.homepage.whats-hot.destroy', $box->id, false)"
                                        method="DELETE"
                                        label="Delete"
                                        trigger-class="text-xs font-medium text-error-600 hover:underline dark:text-error-400"
                                        title="Delete this box?"
                                        message="It comes off the homepage for good. If you only want it gone for now, hide it instead."
                                        confirm="Yes, delete it" />
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">
                                No boxes yet, so this section renders an empty carousel. Add one below,
                                or hide the whole section with the toggle above.
                            </p>
                        @endforelse

                        <form method="post" action="{{ route('admin.homepage.whats-hot.store', [], false) }}"
                            class="space-y-4 rounded-lg border border-dashed border-gray-300 p-4 dark:border-gray-700">
                            @csrf

                            <h4 class="text-sm font-medium text-gray-800 dark:text-white/90">Add a box</h4>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-admin.field label="Links to" name="category_id" :required="true"
                                    hint="Only real, live categories — which is why this is a list and not a URL box.">
                                    <x-admin.select name="category_id"
                                        :options="$linkableCategories->mapWithKeys(fn ($c) => [$c->id => str_repeat('— ', $c->depth).$c->name])->all()"
                                        placeholder="— choose a category" required />
                                </x-admin.field>

                                <x-admin.field label="Headline" name="headline" :required="true"
                                    hint="Short. The theme sets it large over up to three lines.">
                                    <x-admin.input name="headline" :value="old('headline')" required />
                                </x-admin.field>

                                <x-admin.field label="Small line above" name="tagline">
                                    <x-admin.input name="tagline" :value="old('tagline')" />
                                </x-admin.field>

                                <x-admin.field label="Button text" name="link_label"
                                    hint="Defaults to “Shop Now”.">
                                    <x-admin.input name="link_label" :value="old('link_label')" />
                                </x-admin.field>
                            </div>

                            <x-admin.field label="Background image URL" name="image_url"
                                hint="Optional. Without one the box shows the theme's grey placeholder.">
                                <x-admin.input name="image_url" type="url" :value="old('image_url')"
                                    placeholder="https://example.com/promo.jpg" />
                            </x-admin.field>

                            <x-admin.button type="submit" size="sm">Add box</x-admin.button>
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
