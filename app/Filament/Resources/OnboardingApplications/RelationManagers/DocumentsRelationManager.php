<?php

namespace App\Filament\Resources\OnboardingApplications\RelationManagers;

use App\Domain\Onboarding\Actions\GenerateOnboardingDocumentUrlAction;
use App\Domain\Onboarding\Actions\VerifyOnboardingDocumentAction;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Js;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function isReadOnly(): bool
    {
        // No create/edit/delete — documents are acted on only via the custom actions below.
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state): string => (string) str($state->value)->headline()),
                TextColumn::make('verification_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (VerificationStatus $state): string => match ($state) {
                        VerificationStatus::Pending => 'warning',
                        VerificationStatus::Verified => 'success',
                        VerificationStatus::Rejected => 'danger',
                    }),
                TextColumn::make('original_filename')->label('File'),
                TextColumn::make('verified_at')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (): bool => $this->currentUser()->can('view_onboarding_applications'))
                    ->action(function (OnboardingDocument $record): void {
                        $url = app(GenerateOnboardingDocumentUrlAction::class)->execute($record, $this->currentUser());
                        $this->js('window.open('.Js::from($url).", '_blank')");
                    }),
                Action::make('verify')
                    ->label('Verify')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->currentUser()->can('process_onboarding_applications'))
                    ->action(function (OnboardingDocument $record): void {
                        app(VerifyOnboardingDocumentAction::class)
                            ->execute($record, $this->currentUser(), VerificationStatus::Verified);
                        Notification::make()->success()->title('Document verified')->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (): bool => $this->currentUser()->can('process_onboarding_applications'))
                    ->schema([
                        Textarea::make('notes')->label('Why is this document rejected?')->required(),
                    ])
                    ->action(function (array $data, OnboardingDocument $record): void {
                        app(VerifyOnboardingDocumentAction::class)
                            ->execute($record, $this->currentUser(), VerificationStatus::Rejected, $data['notes']);
                        Notification::make()->success()->title('Document rejected')->send();
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
