<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Products</x-slot>
            <p class="text-3xl font-semibold">{{ number_format($productCount) }}</p>
            <p class="text-sm text-gray-500">{{ number_format($activeCount) }} active</p>
        </x-filament::section>

        <x-filament::section class="sm:col-span-2">
            <x-slot name="heading">Last synced</x-slot>
            <p class="text-lg">
                {{ $lastSyncedAt ? \Illuminate\Support\Carbon::parse($lastSyncedAt)->diffForHumans() : 'Never' }}
            </p>
            @if ($lastSyncedAt)
                <p class="text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($lastSyncedAt)->toDayDateTimeString() }}</p>
            @endif
            <p class="mt-3 text-sm text-gray-500">
                Products mirror Zoho Books one-way. Incremental sync runs every 30 minutes; a full sync runs nightly.
                Use &ldquo;Sync now&rdquo; to trigger a full sync immediately.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
