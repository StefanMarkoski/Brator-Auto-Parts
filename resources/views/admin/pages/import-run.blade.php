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

                    @php($changes = $preview['created'] + $preview['updated'])
                    @php($applyLabel = 'Apply '.number_format($changes).' change'.($changes === 1 ? '' : 's'))

                    {{--
                        DOUBLE-SUBMIT GUARD. Applying writes thousands of rows synchronously inside
                        this request, with no progress feedback and no enclosing transaction — so a
                        staff member who sees nothing happen for tens of seconds clicks again, and
                        the second run overlaps the first against the same staging rows. `busy`
                        disables the button on the first submit and swaps the label, so the click
                        that produced nothing visible now says it is working.

                        Inline Alpine rather than resources/js/admin.js on purpose: public/build is
                        gitignored, so anything added there does nothing until somebody runs
                        `npm run build`. This needs no build step.

                        It is only an enhancement — the form is a plain POST with a real submit
                        button, so it still works if Alpine never boots, and the controller refuses
                        a second apply anyway because the run is no longer Queued.

                        x-bind:disabled, not the `:disabled` shorthand: on a Blade component `:` means
                        "PHP expression", and this component already has its own `disabled` prop.
                        The house rule is full directive names for exactly this reason. The static
                        nothing-to-apply case is folded into the expression so Alpine cannot re-enable
                        a button PHP deliberately disabled.

                        NO x-cloak, deliberately — this introduces no element whose first paint would
                        be wrong. The label is rendered by PHP as the slot and x-text's boot value is
                        the same string, so Alpine replacing it changes nothing on screen; the panel's
                        flash-before-boot problem needs an element whose initial state DIFFERS from
                        its Alpine state, and there is none here.
                    --}}
                    <form method="post" action="{{ route('admin.imports.apply', $run->id, false) }}"
                        x-data="{ busy: false }" x-on:submit="busy = true">
                        @csrf
                        <x-admin.button type="submit" :disabled="$changes === 0"
                            x-bind:disabled="busy || {{ $changes === 0 ? 'true' : 'false' }}"
                            x-text="busy ? 'Applying…' : '{{ $applyLabel }}'">{{ $applyLabel }}</x-admin.button>
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

                    @if ($run->rows_created > 0 && $run->reverted_at === null)
                        <x-admin.alert variant="info">
                            New products arrive <strong>unpublished</strong> — a feed can add hundreds
                            in one click, and a supplier's typo should not be live before anyone has
                            read it. Publish them from the product list when you have looked.
                        </x-admin.alert>
                    @endif

                    @if ($run->reverted_at !== null)
                        <x-admin.alert variant="warning" title="Undone">
                            The {{ number_format($run->rows_created) }} product{{ $run->rows_created === 1 ? '' : 's' }}
                            this import created {{ $run->rows_created === 1 ? 'was' : 'were' }} removed
                            {{ $run->reverted_at->diffForHumans() }}. The run itself is kept, because
                            "we imported these and then took them back out" is a more useful history
                            than a gap.
                        </x-admin.alert>
                    @endif
                </x-admin.component-card>

                {{--
                    UNDO, for testing a feed repeatedly. Its own card rather than a link in the
                    Result box: it destroys rows, and a destructive control should not sit where
                    somebody's eye is already scanning numbers.
                --}}
                <x-admin.component-card class="mt-6" title="Undo this import"
                    desc="Removes the products this import created, so the same file can be applied again from scratch.">
                    @if ($cannotRevert !== null)
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cannotRevert }}</p>
                    @else
                        <ul class="mb-5 space-y-2 text-xs text-gray-500 dark:text-gray-400">
                            <li>Deletes the <strong>{{ number_format($run->rows_created) }}</strong>
                                product{{ $run->rows_created === 1 ? '' : 's' }} this run created, for
                                good — not to the trash, so the SKUs are free to import again.</li>
                            @if ($run->rows_updated > 0)
                                <li>Leaves the <strong>{{ number_format($run->rows_updated) }}</strong>
                                    product{{ $run->rows_updated === 1 ? '' : 's' }} it merely
                                    <em>updated</em> completely alone. The feed does not record what it
                                    overwrote, so there is nothing to put back — and those products
                                    existed before it ran.</li>
                            @endif
                            <li>A brand the feed created is removed too, but only if nothing else is
                                left in it.</li>
                            <li>Receipts are safe. A receipt line keeps its own copy of the name,
                                price and VAT; it simply stops linking through to a product.</li>
                        </ul>

                        <x-admin.confirm-action
                            :action="route('admin.imports.destroy', $run->id, false)"
                            method="DELETE"
                            label="Undo this import"
                            trigger-class="inline-flex h-11 items-center rounded-lg bg-error-600 px-4 text-sm font-medium text-white transition hover:bg-error-700"
                            title="Undo this import?"
                            :message="'This deletes '.number_format($run->rows_created).' product'.($run->rows_created === 1 ? '' : 's').' permanently. Products the import only updated are left as they are.'"
                            confirm="Yes, undo it" />
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
