<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')->label('Order')->searchable()->sortable(),
                TextColumn::make('company.legal_name')->label('Company')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => (string) str($state->value)->headline())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Placed => 'info',
                        OrderStatus::Accepted => 'success',
                        OrderStatus::Rejected, OrderStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('zoho_push_status')
                    ->label('Zoho')
                    ->badge()
                    ->formatStateUsing(fn (ZohoPushStatus $state): string => (string) str($state->value)->headline())
                    ->color(fn (ZohoPushStatus $state): string => match ($state) {
                        ZohoPushStatus::Pending => 'gray',
                        ZohoPushStatus::Pushed => 'success',
                        ZohoPushStatus::Failed => 'danger',
                    }),
                TextColumn::make('subtotal_ex_vat')
                    ->label('Total (ex VAT)')
                    ->money(fn (Order $record): string => $record->currency),
                TextColumn::make('created_at')->label('Placed')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class),
                SelectFilter::make('zoho_push_status')->label('Zoho push')->options(ZohoPushStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
