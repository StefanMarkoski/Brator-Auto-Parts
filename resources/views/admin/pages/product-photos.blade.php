@extends('admin.layouts.admin')
@section('title', 'Product photos')
@section('heading', 'Product photos')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Product photos" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was changed" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    <x-admin.alert variant="info" title="Why this screen exists" class="mb-6">
        The purchased theme ships <strong>no product photography</strong> — its product files are
        206&times;206 at 1&nbsp;kB and its four detail-page images are byte-identical, so every
        seeded product shows a grey square. Nobody is going to upload 5,000 photographs by hand, so
        one set per department covers the catalogue in eight downloads.
        <br /><br />
        <strong>A product's own photographs are never touched.</strong> Upload real pictures to a
        few products on their own edit screens and those stay exactly as they are, however many
        times this runs — that is what makes it safe to press.
    </x-admin.alert>

    <div class="space-y-4">
        @foreach ($departments as $department)
            <x-admin.component-card :title="$department['model']->name"
                :desc="number_format($department['products']).' products'
                    .($department['own'] > 0 ? ' — '.$department['own'].' with photographs of their own' : '')">

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <form method="post"
                            action="{{ route('admin.product-photos.store', $department['model']->id, false) }}"
                            class="space-y-4">
                            @csrf

                            <x-admin.field label="Image URLs" :name="'urls-'.$department['model']->id"
                                :hint="'One per line, up to '.$maxPhotos.'. The first becomes the card image; the rest fill the product page. Each is downloaded once and served from this shop, never linked to.'">
                                <x-admin.textarea :name="'urls'" rows="3"
                                    placeholder="https://example.com/brake-disc.jpg&#10;https://example.com/brake-disc-2.jpg" />
                            </x-admin.field>

                            <x-admin.button type="submit">
                                Apply to {{ number_format($department['products'] - $department['own']) }} products
                            </x-admin.button>
                        </form>
                    </div>

                    <div>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $department['photos'] === [] ? 'No bulk photos yet' : 'Currently showing' }}
                        </p>

                        @if ($department['photos'] !== [])
                            <div class="flex flex-wrap gap-2">
                                @foreach ($department['photos'] as $path)
                                    <img src="/{{ $path }}" alt=""
                                        class="h-16 w-16 rounded-lg border border-gray-200 object-cover dark:border-gray-800" />
                                @endforeach
                            </div>

                            <div class="mt-3">
                                <x-admin.confirm-action
                                    :action="route('admin.product-photos.destroy', $department['model']->id, false)"
                                    method="DELETE"
                                    label="Remove these"
                                    trigger-class="text-xs font-medium text-error-600 hover:underline dark:text-error-400"
                                    :title="'Remove '.$department['model']->name.' photos?'"
                                    message="The products go back to showing no image. Products with photographs of their own are untouched."
                                    confirm="Yes, remove them" />
                            </div>
                        @endif
                    </div>
                </div>
            </x-admin.component-card>
        @endforeach
    </div>
@endsection
