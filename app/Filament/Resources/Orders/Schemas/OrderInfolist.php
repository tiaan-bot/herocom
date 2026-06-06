<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Domain\Ordering\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number')->label('Order number'),
                        TextEntry::make('company.legal_name')->label('Company'),
                        TextEntry::make('placedBy.name')->label('Placed by')->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->label('Placed')->dateTime(),
                        TextEntry::make('subtotal_ex_vat')->label('Subtotal (ex VAT)')->money(fn (Order $record): string => $record->currency),
                    ]),

                Section::make('Zoho push')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('zoho_push_status')->label('Push status')->badge(),
                        TextEntry::make('zoho_salesorder_id')->label('Sales order ID')->placeholder('—'),
                        TextEntry::make('zoho_pushed_at')->label('Pushed at')->dateTime()->placeholder('—'),
                        TextEntry::make('zoho_push_error')->label('Last error')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name')->columnSpan(2),
                                TextEntry::make('quantity'),
                                TextEntry::make('line_total_ex_vat')->label('Total')->money('ZAR'),
                            ]),
                    ]),

                Section::make('Delivery')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('delivery_address_line1')->label('Address')
                            ->formatStateUsing(fn (Order $record): string => collect([
                                $record->delivery_address_line1,
                                $record->delivery_address_line2,
                                $record->delivery_city,
                                $record->delivery_province,
                                $record->delivery_postal_code,
                            ])->filter()->join(', '))
                            ->columnSpanFull(),
                        TextEntry::make('customer_note')->label('Customer note')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Decision trail')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('accepted_at')->dateTime()->placeholder('—'),
                        TextEntry::make('rejected_at')->dateTime()->placeholder('—'),
                        TextEntry::make('rejection_reason')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
