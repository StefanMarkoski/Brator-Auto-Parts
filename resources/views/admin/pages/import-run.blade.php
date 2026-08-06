@extends('admin.layouts.admin')
@section('title', 'Import run')
@section('heading', 'Import run')

@section('content')
    @use('App\Domain\CatalogImport\Enums\ImportRunStatus')
    @use('App\Domain\CatalogImport\Enums\StagingRowOutcome')

    <x-admin.page-breadcrumb :pageTitle="$run->source?->name ?? 'Import run'" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="Nothing was changed" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 space-y-6">
            @if ($preview !== null)
                {{--
                    The preview is a DRY RUN, recomputed on every visit rather than stored, so it
                    reflects the catalogue as it is now. A preview computed at upload time and read
                    back an hour later would describe a shop that has since moved on.
                --}}
                <x-admin.component-card title="What this will do"
                    desc="Nothing has been written yet. These are the counts as of right now.">
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ([
                            ['New products', $preview['created'], 'success'],
                            ['Updated', $preview['updated'], 'primary'],
                            ['Skipped', $preview['skipped'], 'warning'],
                            ['Failed', $preview['failed'], 'error'],
                        ] as [$label, $value, $colour])
                            <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($value) }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($preview['notes'] !== [])
                        <div class="space-y-1.5 border-t border-gray-100 pt-4 dark:border-gray-800">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Rows worth reading</p>
                            @foreach ($preview['notes'] as $note)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $note }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="post" action="{{ route('admin.imports.apply', $run->id, false) }}">
                        @csrf
                        <x-admin.button type="submit" :disabled="$preview['created'] + $preview['updated'] === 0">
                            Apply {{ number_format($preview['created'] + $preview['updated']) }} change{{ $preview['created'] + $preview['updated'] === 1 ? '' : 's' }}
                        </x-admin.button>
                    </form>
                </x-admin.component-card>
            @else
                <x-admin.component-card title="Result"
                    :desc="'Applied '.($run->finished_at?->diffForHumans() ?? '')">
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ([
                            ['Created', $run->rows_created],
                            ['Updated', $run->rows_updated],
                            ['Skipped', $run->rows_skipped],
                            ['Failed', $run->rows_failed],
                        ] as [$label, $value])
                            <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                <p class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ number_format($value) }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($run->rows_created > 0)
                        <x-admin.alert variant="info">
                            New products arrive <strong>unpublished</strong> — a feed can add hundreds
                            in one click, and a supplier's typo should not be live before anyone has
                            read it. Publish them from the product list when you have looked.
                        </x-admin.alert>
                    @endif
                </x-admin.component-card>
            @endif

            <x-admin.component-card title="Rows"
                :desc="'First '.min(50, $run->rows_total).' of '.number_format($run->rows_total).'.'">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-400">
                            <tr>
                                <th class="pb-3 pr-4">Line</th>
                                <th class="pb-3 pr-4">SKU</th>
                                <th class="pb-3 pr-4">Name</th>
                                <th class="pb-3 pr-4">Brand</th>
                                <th class="pb-3 pr-4 text-right">Price</th>
                                <th class="pb-3 pr-4">Outcome</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                @php($payload = $row->payload)
                                <tr>
                                    <td class="py-3 pr-4 text-gray-400">{{ $payload['line'] ?? '—' }}</td>
                                    <td class="py-3 pr-4">
                                        @if ($row->product_id)
                                            <a href="{{ route('admin.products.edit', $row->product_id, false) }}"
                                                class="text-brand-500 hover:underline">{{ $payload['sku'] ?? '' }}</a>
                                        @else
                                            <span class="text-gray-700 dark:text-gray-300">{{ $payload['sku'] ?? '' }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 pr-4 text-gray-700 dark:text-gray-300">{{ $payload['name'] ?? '' }}</td>
                                    <td class="py-3 pr-4 text-gray-500">{{ $payload['brand'] ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-right text-gray-500">{{ $payload['price_net'] ?? '' }}</td>
                                    <td class="py-3 pr-4">
                                        <x-admin.badge size="sm" :color="match ($row->outcome) {
                                            StagingRowOutcome::Created => 'success',
                                            StagingRowOutcome::Updated => 'primary',
                                            StagingRowOutcome::Skipped => 'warning',
                                            StagingRowOutcome::Failed => 'error',
                                            default => 'light',
                                        }">{{ ucfirst($row->outcome->value) }}</x-admin.badge>

                                        @if ($row->error)
                                            <span class="mt-1 block text-xs text-gray-400">{{ $row->error }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.component-card>
        </div>

        <div class="space-y-6">
            <x-admin.component-card title="Run">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Supplier</dt>
                        <dd class="font-medium text-gray-800 dark:text-white/90">{{ $run->source?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd>
                            <x-admin.badge size="sm" :color="match ($run->status) {
                                ImportRunStatus::Completed => 'success',
                                ImportRunStatus::Failed => 'error',
                                ImportRunStatus::Running => 'primary',
                                default => 'warning',
                            }">{{ ucfirst($run->status->value) }}</x-admin.badge>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Rows in file</dt>
                        <dd class="font-medium text-gray-800 dark:text-white/90">{{ number_format($run->rows_total) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Uploaded</dt>
                        <dd class="text-gray-500">{{ $run->created_at?->format('d M Y H:i') }}</dd>
                    </div>
                </dl>

                <x-admin.button variant="outline" size="sm"
                    :href="route('admin.imports.index', [], false)">All imports</x-admin.button>
            </x-admin.component-card>

            <x-admin.component-card title="What an import will not do">
                <ul class="space-y-2 text-xs text-gray-500 dark:text-gray-400">
                    <li>It never overwrites a field you have edited by hand — those are listed as
                        "yours" on the product, and the row says what it left alone.</li>
                    <li>It never clears a value because a cell was blank.</li>
                    <li>It never deactivates a product just because the feed stopped listing it.</li>
                    <li>It never creates a category. A department is navigation, not supplier data.</li>
                </ul>
            </x-admin.component-card>
        </div>
    </div>
@endsection
