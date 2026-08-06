@extends('admin.layouts.admin')
@section('title', 'Brands')
@section('heading', 'Brands')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Brands" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was changed" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    @if ($errors->any())
        <x-admin.alert variant="error" title="Check the form" class="mb-6">{{ $errors->first() }}</x-admin.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-admin.component-card title="Brands" :desc="number_format($brands->total()).' manufacturers'">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="pb-3 pr-4">Brand</th>
                                <th class="pb-3 pr-4">Slug</th>
                                <th class="pb-3 pr-4 text-right">Products</th>
                                <th class="pb-3 pr-4">Status</th>
                                <th class="pb-3 pr-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($brands as $brand)
                                <tr>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $brand->name }}</td>
                                    <td class="py-3 pr-4 text-gray-400"><code>{{ $brand->slug }}</code></td>
                                    <td class="py-3 pr-4 text-right">
                                        @if ($brand->products_count > 0)
                                            {{-- Links to the shop filtered by this brand, so the
                                                 number is checkable rather than just a number. --}}
                                            <a href="{{ route('search', ['brand' => [$brand->slug]], false) }}"
                                                target="_blank" class="text-brand-500 hover:underline">
                                                {{ number_format($brand->products_count) }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4">
                                        <x-admin.badge size="sm" :color="$brand->is_active ? 'success' : 'light'">
                                            {{ $brand->is_active ? 'Active' : 'Hidden' }}
                                        </x-admin.badge>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <button type="button"
                                                class="text-sm font-medium text-brand-500 hover:underline"
                                                x-on:click="$dispatch('edit-brand', {{ Js::from($brand->only(['id', 'name', 'slug', 'description', 'is_active'])) }})">
                                                Edit
                                            </button>

                                            <x-admin.confirm-action
                                                :action="route('admin.brands.destroy', $brand->id, false)"
                                                method="DELETE"
                                                label="Delete"
                                                trigger-class="text-sm font-medium text-error-600 hover:underline dark:text-error-400"
                                                :disabled="$brand->products_count > 0"
                                                :disabled-reason="number_format($brand->products_count).' products carry this brand. Reassign them first.'"
                                                :title="'Delete '.$brand->name.'?'"
                                                message="It disappears from the brand filter and the brand strip on the homepage. No products are affected — this one has none."
                                                confirm="Yes, delete it" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">{{ $brands->links() }}</div>
            </x-admin.component-card>
        </div>

        <div x-data="{
                id: null, name: '', slug: '', description: '', is_active: true,
                reset() { this.id = null; this.name = ''; this.slug = ''; this.description = ''; this.is_active = true; },
            }"
            x-on:edit-brand.window="
                id = $event.detail.id;
                name = $event.detail.name;
                slug = $event.detail.slug;
                description = $event.detail.description ?? '';
                is_active = $event.detail.is_active;
                $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            ">

            <x-admin.component-card title="Add or edit a brand"
                desc="The slug is what the brand filter and every brand link use, so changing it breaks links already shared.">
                <form method="post" x-bind:action="id
                        ? '{{ route('admin.brands.index', [], false) }}/' + id
                        : '{{ route('admin.brands.store', [], false) }}'"
                    class="space-y-5">
                    @csrf
                    <template x-if="id">
                        <input type="hidden" name="_method" value="PUT" />
                    </template>

                    <x-admin.field label="Name" name="name" :required="true">
                        <x-admin.input name="name" x-model="name" required />
                    </x-admin.field>

                    <x-admin.field label="URL slug" name="slug" hint="Leave blank to build it from the name.">
                        <x-admin.input name="slug" x-model="slug" placeholder="bosch" />
                    </x-admin.field>

                    <x-admin.field label="Description" name="description">
                        <x-admin.textarea name="description" rows="3" x-model="description" />
                    </x-admin.field>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-400">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" x-model="is_active"
                            class="rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                        Visible in the shop
                    </label>

                    <div class="flex gap-3">
                        <x-admin.button type="submit">
                            <span x-text="id ? 'Save changes' : 'Add brand'"></span>
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
