<?php

namespace App\Filament\Resources\OnboardingApplications\RelationManagers;

use App\Domain\Onboarding\Actions\RevealPrincipalIdAction;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Domain\Onboarding\Models\OnboardingPrincipal;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PrincipalsRelationManager extends RelationManager
{
    protected static string $relationship = 'principals';

    protected static ?string $title = 'Principals & sureties';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Principals only exist on the credit branch.
        return $ownerRecord instanceof OnboardingApplication
            && $ownerRecord->account_type_requested === AccountType::Credit;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name'),
                TextColumn::make('surname'),
                TextColumn::make('shareholding_percent')->label('Shareholding %')->suffix('%')->placeholder('—'),
                // ID masked by default; full value only via the audited Reveal action.
                TextColumn::make('id_number')
                    ->label('ID number')
                    ->formatStateUsing(fn (string $state): string => '•••••••'.substr($state, -4)),
                TextColumn::make('residential_city')->label('City')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('revealId')
                    ->label('Reveal ID')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (): bool => $this->currentUser()->can('process_onboarding_applications'))
                    ->requiresConfirmation()
                    ->modalDescription('Revealing this ID number is logged against your account.')
                    ->action(function (OnboardingPrincipal $record): void {
                        $idNumber = app(RevealPrincipalIdAction::class)->execute($record, $this->currentUser());
                        Notification::make()
                            ->title('ID number')
                            ->body($idNumber)
                            ->persistent()
                            ->send();
                    }),
            ]);
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
