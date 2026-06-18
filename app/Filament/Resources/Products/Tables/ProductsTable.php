<?php

namespace App\Filament\Resources\Products\Tables;

use App\Domain\Catalog\Enums\ProductStatus;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                // Read-only thumbnail; image_url is the gated catalog.image route
                // (a full URL), mirrored one-way from Zoho. Empty when no image.
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->square()
                    ->size(40),
                TextColumn::make('name')->searchable()->sortable()->limit(60),
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('brand')->searchable()->sortable(),
                TextColumn::make('category')->searchable()->sortable(),
                TextColumn::make('stock_on_hand')->label('Stock')->numeric()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => (string) str($state->value)->headline())
                    ->color(fn (ProductStatus $state): string => $state === ProductStatus::Active ? 'success' : 'gray'),
                ToggleColumn::make('is_featured')->label('Featured')->sortable(),
                // Read-only: Zoho owns this via the "Sync to portal" checkbox.
                IconColumn::make('sync_to_portal')
                    ->label('Synced')
                    ->boolean()
                    ->sortable()
                    ->tooltip('Controlled by the "Sync to portal" checkbox in Zoho (Items). Read-only here — it cannot be edited in the portal and is overwritten on every sync.'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProductStatus::class),
                TernaryFilter::make('is_featured')->label('Featured'),
                TernaryFilter::make('sync_to_portal')->label('Synced to portal'),
            ]);
    }
}
