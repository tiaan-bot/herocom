<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Products</x-slot>
            <p class="text-3xl font-semibold">{{ number_format($productCount) }}</p>
            <p class="text-sm text-gray-500">{{ number_format($activeProductCount) }} active</p>
            <p class="mt-3 text-sm text-gray-500">
                Last synced:
                {{ $productsLastSyncedAt ? \Illuminate\Support\Carbon::parse($productsLastSyncedAt)->diffForHumans() : 'Never' }}
            </p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Invoices</x-slot>
            <p class="text-3xl font-semibold">{{ number_format($invoiceCount) }}</p>
            <p class="text-sm text-gray-500">mirrored from Zoho</p>
            <p class="mt-3 text-sm text-gray-500">
                Last synced:
                {{ $invoicesLastSyncedAt ? \Illuminate\Support\Carbon::parse($invoicesLastSyncedAt)->diffForHumans() : 'Never' }}
            </p>
        </x-filament::section>
    </div>

    <p class="mt-4 text-sm text-gray-500">
        Products and invoices mirror Zoho Books one-way. Incremental syncs run every 30 minutes; full syncs run nightly.
        Use the buttons above to queue a full sync now.
    </p>
</x-filament-panels::page>
