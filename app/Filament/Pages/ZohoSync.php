<?php

namespace App\Filament\Pages;

use App\Domain\Billing\Jobs\SyncZohoInvoices;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Jobs\SyncZohoProducts;
use App\Domain\Catalog\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ZohoSync extends Page
{
    protected string $view = 'filament.pages.zoho-sync';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $title = 'Zoho Sync';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_catalog_sync') ?? false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncProducts')
                ->label('Sync products')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->action(function (): void {
                    SyncZohoProducts::dispatch(full: true);
                    Notification::make()->success()->title('Product sync queued')->send();
                }),
            Action::make('syncInvoices')
                ->label('Sync invoices')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->action(function (): void {
                    SyncZohoInvoices::dispatch(full: true);
                    Notification::make()->success()->title('Invoice sync queued')->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'productCount' => Product::query()->count(),
            'activeProductCount' => Product::query()->where('status', ProductStatus::Active)->count(),
            'productsLastSyncedAt' => Product::query()->max('last_synced_at'),
            'invoiceCount' => Invoice::query()->count(),
            'invoicesLastSyncedAt' => Invoice::query()->max('last_synced_at'),
        ];
    }
}
