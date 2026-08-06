@extends('admin.layouts.admin')
@section('title', 'Categories')
@section('heading', 'Categories')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Categories" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was changed" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    @if ($errors->any())
        <x-admin.alert variant="error" title="Check the form" class="mb-6">
            {{ $errors->first() }}
        </x-admin.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Category tree"
                desc="Sub-categories drive the listing filters — each carries its own filter set. Renaming one rewrites the URLs beneath it.">
                <div class="space-y-4">
                    @foreach ($categories as $parent)
                        <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <a href="{{ route('shop.category', $parent->slug, false) }}" target="_blank"
                                        class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $parent->name }}</a>
                                    {{-- The subtree count: what the department's own page
                                         shows. The direct count is 0 for every department,
                                         because parts are filed against sub-categories. --}}
                                    <span class="ml-1 text-xs text-gray-400">{{ number_format($parent->subtree_products_count) }} parts</span>
                                    @unless ($parent->is_active)
                                        <x-admin.badge color="light" size="sm" class="ml-2">Hidden</x-admin.badge>
                                    @endunless
                                    <code class="mt-1 block text-xs text-gray-400">{{ $parent->path }}</code>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <button type="button" class="text-sm font-medium text-brand-500 hover:underline"
                                        x-on:click="$dispatch('edit-category', {{ Js::from($parent->only(['id', 'name', 'slug', 'description', 'parent_id', 'is_active'])) }})">
                                        Edit
                                    </button>

                                    {{-- Disabled with the reason on it rather than hidden: a
                                         control that vanishes leaves you wondering whether you
                                         are allowed, and one that fails after a click wastes
                                         the click. --}}
                                    <x-admin.confirm-action
                                        :action="route('admin.categories.destroy', $parent->id, false)"
                                        method="DELETE"
                                        label="Delete"
                                        trigger-class="text-sm font-medium text-error-600 hover:underline dark:text-error-400"
                                        :disabled="$parent->products_count > 0 || $parent->children->isNotEmpty()"
                                        :disabled-reason="$parent->products_count > 0
                                            ? number_format($parent->products_count).' products use this category. Move them first.'
                                            : 'This category has sub-categories. Delete or move those first.'"
                                        :title="'Delete '.$parent->name.'?'"
                                        message="The category is removed from the shop's navigation. No products are affected — this one has none."
                                        confirm="Yes, delete it" />
                                </div>
                            </div>

                            <ul class="mt-3 space-y-1.5 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                                @forelse ($parent->children as $child)
                                    <li class="flex items-center justify-between gap-3">
                                        <span>
                                            <a href="{{ route('shop.category', $child->slug, false) }}" target="_blank"
                                                class="text-gray-600 hover:text-brand-500 dark:text-gray-400">{{ $child->name }}</a>
                                            <span class="ml-1 text-xs text-gray-400">{{ number_format($child->products_count) }}</span>
                                            @unless ($child->is_active)
                                                <x-admin.badge color="light" size="sm" class="ml-2">Hidden</x-admin.badge>
                                            @endunless
                                        </span>

                                        <span class="flex shrink-0 items-center gap-3">
                                            <button type="button" class="text-xs font-medium text-brand-500 hover:underline"
                                                x-on:click="$dispatch('edit-category', {{ Js::from($child->only(['id', 'name', 'slug', 'description', 'parent_id', 'is_active'])) }})">
                                                Edit
                                            </button>

                                            <x-admin.confirm-action
                                                :action="route('admin.categories.destroy', $child->id, false)"
                                                method="DELETE"
                                                label="Delete"
                                                trigger-class="text-xs font-medium text-error-600 hover:underline dark:text-error-400"
                                                :disabled="$child->products_count > 0"
                                                :disabled-reason="number_format($child->products_count).' products use this category. Move them first.'"
                                                :title="'Delete '.$child->name.'?'"
                                                message="It disappears from the filter sidebar and the department page. No products are affected — this one has none."
                                                confirm="Yes, delete it" />
                                        </span>
                                    </li>
                                @empty
                                    <li class="text-sm text-gray-400">No sub-categories yet.</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-admin.component-card>
        </div>

        {{--
            One form for both create and edit, switched by the Alpine state below. The Edit
            buttons above dispatch the row's values into it rather than each row carrying its
            own hidden form — sixty inline forms is sixty chances for two of them to collide
            on an id.
        --}}
        <div x-data="{
                id: null,
                name: '',
                slug: '',
                description: '',
                parent_id: '',
                is_active: true,
                reset() {
                    this.id = null; this.name = ''; this.slug = '';
                    this.description = ''; this.parent_id = ''; this.is_active = true;
                },
            }"
            x-on:edit-category.window="
                id = $event.detail.id;
                name = $event.detail.name;
                slug = $event.detail.slug;
                description = $event.detail.description ?? '';
                parent_id = $event.detail.parent_id ?? '';
                is_active = $event.detail.is_active;
                $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            ">

            <x-admin.component-card title="Add or edit a category"
                desc="A sub-category is what shoppers filter by. Departments hold sub-categories.">
                <form method="post" x-bind:action="id
                        ? '{{ route('admin.categories.index', [], false) }}/' + id
                        : '{{ route('admin.categories.store', [], false) }}'"
                    class="space-y-5">
                    @csrf
                    {{-- PUT when editing, POST when creating. The hidden _method is only
                         emitted for the update case. --}}
                    <template x-if="id">
                        <input type="hidden" name="_method" value="PUT" />
                    </template>

                    <x-admin.field label="Name" name="name" :required="true">
                        <x-admin.input name="name" x-model="name" required />
                    </x-admin.field>

                    <x-admin.field label="URL slug" name="slug"
                        hint="Leave blank to build it from the name. Changing it rewrites the URLs of everything beneath.">
                        <x-admin.input name="slug" x-model="slug" placeholder="brake-discs" />
                    </x-admin.field>

                    <x-admin.field label="Parent department" name="parent_id"
                        hint="Leave as a department to make this a top-level entry in the nav.">
                        <x-admin.select name="parent_id" x-model="parent_id"
                            :options="$parents->pluck('name', 'id')->all()"
                            placeholder="— none, this is a department" />
                    </x-admin.field>

                    <x-admin.field label="Description" name="description">
                        <x-admin.textarea name="description" rows="3" x-model="description" />
                    </x-admin.field>

                    {{-- Not the toggle component here: that one owns its own state from a
                         server-rendered value, and this form is repopulated by Alpine. --}}
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" x-model="is_active"
                            class="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                        Visible in the shop
                    </label>

                    <div class="flex gap-3">
                        <x-admin.button type="submit">
                            <span x-text="id ? 'Save changes' : 'Add category'"></span>
                        </x-admin.button>

                        <template x-if="id">
                            <x-admin.button variant="outline" x-on:click="reset()">New instead</x-admin.button>
                        </template>
                    </div>
                </form>
            </x-admin.component-card>
        </div>
    </div>
@endsection
