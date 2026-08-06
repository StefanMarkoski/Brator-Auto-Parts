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
            </x-admin.component-card>
        @endforeach
    </div>

    <div class="mt-6">
        <x-admin.button variant="outline" :href="route('home', [], false)" target="_blank">
            Open the homepage
        </x-admin.button>
    </div>
@endsection
