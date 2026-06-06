<?php

namespace App\Filament\Resources\Products;

use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Tables\ProductsTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Products';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_catalog_sync') ?? false;
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
        ];
    }

    // Products mirror Zoho one-way — never created or edited here. Staff only toggle
    // the local `is_featured` curation flag via the table.
    public static function canCreate(): bool
    {
        return false;
    }
}
