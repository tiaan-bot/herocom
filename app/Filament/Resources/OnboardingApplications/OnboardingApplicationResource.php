<?php

namespace App\Filament\Resources\OnboardingApplications;

use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Filament\Resources\OnboardingApplications\Pages\ListOnboardingApplications;
use App\Filament\Resources\OnboardingApplications\Pages\ViewOnboardingApplication;
use App\Filament\Resources\OnboardingApplications\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\OnboardingApplications\RelationManagers\PrincipalsRelationManager;
use App\Filament\Resources\OnboardingApplications\RelationManagers\TradeReferencesRelationManager;
use App\Filament\Resources\OnboardingApplications\Schemas\OnboardingApplicationInfolist;
use App\Filament\Resources\OnboardingApplications\Tables\OnboardingApplicationsTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnboardingApplicationResource extends Resource
{
    protected static ?string $model = OnboardingApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Applications';

    protected static string|\UnitEnum|null $navigationGroup = 'Onboarding';

    protected static ?string $recordTitleAttribute = 'contact_name';

    public static function infolist(Schema $schema): Schema
    {
        return OnboardingApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnboardingApplicationsTable::configure($table);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            PrincipalsRelationManager::class,
            TradeReferencesRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOnboardingApplications::route('/'),
            'view' => ViewOnboardingApplication::route('/{record}'),
        ];
    }

    // Applications are never created or free-edited in the admin — they arrive
    // via the public submission Action and are only acted on via gated actions.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('company');
    }
}
