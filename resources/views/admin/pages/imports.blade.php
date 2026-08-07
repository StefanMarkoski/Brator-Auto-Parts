@extends('admin.layouts.admin')
@section('title', 'Imports')
@section('heading', 'Imports')

@section('content')
    <x-admin.page-breadcrumb pageTitle="Imports" />

    @if (session('status'))
        <x-admin.alert variant="success" class="mb-6">{{ session('status') }}</x-admin.alert>
    @endif

    @if (session('error'))
        <x-admin.alert variant="error" title="The file was not accepted" class="mb-6">{{ session('error') }}</x-admin.alert>
    @endif

    @if ($errors->any())
        <x-admin.alert variant="error" title="Check the form" class="mb-6">{{ $errors->first() }}</x-admin.alert>
    @endif

    <x-admin.component-card title="Import a supplier feed" class="mb-6"
        desc="Upload a CSV. Nothing changes until you have seen the preview and pressed Apply.">
        <form method="post" enctype="multipart/form-data"
            action="{{ route('admin.imports.upload', [], false) }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-admin.field label="Supplier" name="source_name" :required="true"
                    hint="Reused if you have imported from them before, so the run history stays together.">
                    <x-admin.input name="source_name" :value="old('source_name')" placeholder="XGate" required />
                </x-admin.field>

                <x-admin.field label="CSV file" name="feed" :required="true"
                    hint="Up to 8MB and 5,000 rows.">
                    <input type="file" name="feed" accept=".csv,text/csv" required
                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-600 file:mr-3 file:rounded file:border-0 file:bg-brand-500 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white dark:border-gray-700 dark:text-gray-400" />
                </x-admin.field>
            </div>

            <div class="rounded-lg border border-gray-100 p-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <p class="mb-2 font-medium text-gray-700 dark:text-gray-300">Columns</p>
                <p><code>sku</code>, <code>name</code> and <code>price_net</code> are required.
                    <code>brand</code>, <code>category</code>, <code>sale_price</code>,
                    <code>stock</code>, <code>condition</code>, <code>short_description</code> and
                    <code>part_number</code> and <code>fits</code> are optional. Any other column is
                    ignored.</p>
                <p class="mt-2"><code>fits</code> is which cars the part fits — the only way to set
                    fitment, and what makes a part findable through the Year/Make/Model picker.
                    Separate several with a semicolon, and write each either as an engine code or in
                    full:</p>
                <p class="mt-1"><code>Opel Astra H 1.7 CDTI;Z19DT;Volkswagen Golf V 1.9 TDI</code></p>
                <p class="mt-2">A part with no <code>fits</code> still imports and still sells — it
                    just will not appear once a shopper picks their car. Fitment is <strong>added</strong>,
                    never replaced, so a second feed cannot delete what a first one recorded. An
                    engine code shared by more than one model matches <strong>nothing</strong> and is
                    reported: the same engine in a different car often takes a different part, so
                    write the make, model and engine in that case.</p>
                <p class="mt-2">A brand we do not have is <strong>created</strong>, so a new supplier
                    appears in the shop's brand filter on its own. A category that does not exist is
                    <strong>refused</strong> — a feed does not get to invent departments.</p>
                <p class="mt-2">An existing SKU is <strong>updated</strong>, and never in a field you
                    have edited by hand. A blank cell means "no opinion", not "clear it". Products
                    missing from the feed are left alone.</p>
            </div>

            <x-admin.button type="submit">Upload and preview</x-admin.button>
        </form>
    </x-admin.component-card>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.component-card title="Sources"
            desc="Where catalogue data comes from. Erasing one removes every part it created, its whole run history and the supplier itself — so the same file imports cleanly again afterwards.">
            @forelse ($sources as $source)
                @php($preview = $purgePreviews[$source->id])

                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ $source->name }}
                        <span class="block text-xs text-gray-400">{{ strtoupper($source->type->value) }} &middot;
                            last run {{ $source->last_run_at?->diffForHumans() ?? 'never' }} &middot;
                            {{ number_format($preview['products']) }} part{{ $preview['products'] === 1 ? '' : 's' }} created</span></span>

                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $source->is_active ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06]' }}">
                            {{ $source->is_active ? 'Active' : 'Paused' }}</span>

                        {{--
                            The confirmation states NUMBERS rather than a warning. This is the one
                            control on the screen that cannot be put back by re-importing, and "are
                            you sure?" with no figures is a question nobody can answer.
                        --}}
                        <x-admin.confirm-action
                            :action="route('admin.imports.sources.purge', $source->id, false)"
                            method="DELETE"
                            label="Erase everything"
                            trigger-class="text-sm font-medium text-error-600 hover:underline dark:text-error-400"
                            :title="'Erase '.$source->name.' and everything it created?'"
                            :message="number_format($preview['products']).' part'.($preview['products'] === 1 ? '' : 's').' will be deleted permanently, along with '
                                .number_format($preview['runs']).' run'.($preview['runs'] === 1 ? '' : 's').' and the supplier itself.'
                                .($preview['kept'] > 0
                                    ? ' '.number_format($preview['kept']).' product'.($preview['kept'] === 1 ? '' : 's').' that this feed only updated will be KEPT — they existed before it.'
                                    : '')
                                .($preview['sold'] > 0
                                    ? ' '.number_format($preview['sold']).' receipt line'.($preview['sold'] === 1 ? '' : 's').' will stop linking to a product; the receipts keep their own record of name, price and VAT.'
                                    : '')
                                .' The same file can be imported again afterwards.'"
                            confirm="Yes, erase it" />
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">No sources configured yet.</p>
            @endforelse
        </x-admin.component-card>

        <x-admin.component-card title="Recent runs">
            @forelse ($runs as $run)
                <div class="text-sm">
                    {{-- Linked: a run history you cannot open is a list of numbers with no story. --}}
                    <a href="{{ route('admin.imports.show', $run->id, false) }}"
                        class="font-medium text-brand-500 hover:underline">{{ $run->source?->name ?? 'Unknown source' }}</a>
                    <span class="block text-xs text-gray-400">
                        {{ $run->status->value }} &middot; {{ number_format($run->rows_created) }} created,
                        {{ number_format($run->rows_updated) }} updated, {{ number_format($run->rows_skipped) }} skipped
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No import runs recorded.</p>
            @endforelse
        </x-admin.component-card>
    </div>
@endsection
