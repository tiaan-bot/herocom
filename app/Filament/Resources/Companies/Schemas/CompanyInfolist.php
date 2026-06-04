<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Domain\Onboarding\Models\Company;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('legal_name'),
                        TextEntry::make('trading_name')->placeholder('—'),
                        TextEntry::make('entity_type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('registration_number')->placeholder('—'),
                        TextEntry::make('vat_number')->placeholder('—'),
                        TextEntry::make('users_count')->label('Users')->state(fn (Company $record): int => $record->users()->count()),
                    ]),

                Section::make('Credit')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('credit_terms')->badge(),
                        TextEntry::make('credit_limit')->money(fn (Company $record): string => $record->credit_limit_currency)->placeholder('—'),
                        TextEntry::make('credit_terms_days')->label('Terms (days)')->suffix(' days')->placeholder('—'),
                        TextEntry::make('discount_percent')->label('Discount')->suffix('%'),
                        TextEntry::make('zoho_customer_id')->label('Zoho customer')->placeholder('Not synced'),
                    ]),
            ]);
    }
}
