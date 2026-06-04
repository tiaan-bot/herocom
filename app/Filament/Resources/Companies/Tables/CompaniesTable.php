<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Models\Company;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('legal_name')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (CompanyStatus $state): string => (string) str($state->value)->headline())
                    ->color(fn (CompanyStatus $state): string => match ($state) {
                        CompanyStatus::Pending => 'warning',
                        CompanyStatus::Approved => 'success',
                        CompanyStatus::Rejected => 'danger',
                        CompanyStatus::Suspended => 'gray',
                    }),
                TextColumn::make('credit_terms')
                    ->label('Terms')
                    ->badge()
                    ->formatStateUsing(fn (CreditTerms $state): string => $state === CreditTerms::OnAccount ? 'On account' : 'EFT upfront'),
                TextColumn::make('credit_limit')
                    ->money(fn (Company $record): string => $record->credit_limit_currency)
                    ->placeholder('—'),
                TextColumn::make('discount_percent')->label('Discount')->suffix('%'),
                TextColumn::make('zoho_customer_id')
                    ->label('Zoho')
                    ->placeholder('Not synced')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(CompanyStatus::class),
                SelectFilter::make('credit_terms')->label('Credit terms')->options(CreditTerms::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
