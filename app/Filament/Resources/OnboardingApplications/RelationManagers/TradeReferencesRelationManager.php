<?php

namespace App\Filament\Resources\OnboardingApplications\RelationManagers;

use App\Domain\Onboarding\Enums\AccountHeld;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Models\OnboardingApplication;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TradeReferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'tradeReferences';

    protected static ?string $title = 'Trade references';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Trade references only exist on the credit branch.
        return $ownerRecord instanceof OnboardingApplication
            && $ownerRecord->account_type_requested === AccountType::Credit;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->label('Company'),
                TextColumn::make('credit_limit')->money(fn ($record): string => $record->credit_limit_currency)->placeholder('—'),
                TextColumn::make('account_held')
                    ->badge()
                    ->formatStateUsing(fn (AccountHeld $state): string => (string) str($state->value)->upper()),
                TextColumn::make('terms_days')->label('Terms')->suffix(' days')->placeholder('—'),
            ]);
    }
}
