<?php

namespace App\Filament\Pages;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Jobs\SyncZohoProducts;
use App\Domain\Catalog\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CatalogSync extends Page
{
    protected string $view = 'filament.pages.catalog-sync';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $title = 'Catalog sync';

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
            Action::make('syncNow')
                ->label('Sync now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Queue a full sync from Zoho Books? Existing products are updated and items removed in Zoho are marked inactive.')
                ->action(function (): void {
                    SyncZohoProducts::dispatch(full: true);
                    Notification::make()->success()->title('Sync queued')->body('A full Zoho product sync has been dispatched.')->send();
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
            'activeCount' => Product::query()->where('status', ProductStatus::Active)->count(),
            'lastSyncedAt' => Product::query()->max('last_synced_at'),
        ];
    }
}
