<?php

namespace App\Filament\Resources\OnboardingApplications\Pages;

use App\Domain\Onboarding\Actions\ApproveOnboardingApplicationAction;
use App\Domain\Onboarding\Actions\RecordCgicOutcomeAction;
use App\Domain\Onboarding\Actions\RejectOnboardingApplicationAction;
use App\Domain\Onboarding\Actions\RequestApplicationInformationAction;
use App\Domain\Onboarding\Actions\ViewCgicPayloadAction;
use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationPdfStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use App\Domain\Onboarding\Exceptions\OnboardingDecisionException;
use App\Domain\Onboarding\Jobs\GenerateApplicationPdf;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Filament\Resources\OnboardingApplications\OnboardingApplicationResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ViewOnboardingApplication extends ViewRecord
{
    protected static string $resource = OnboardingApplicationResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->requestInfoAction(),
            $this->recordCgicAction(),
            $this->viewCgicPayloadAction(),
            $this->regeneratePdfAction(),
            $this->approveAction(),
            $this->rejectAction(),
        ];
    }

    private function regeneratePdfAction(): Action
    {
        return Action::make('regeneratePdf')
            ->label('Regenerate PDF')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (OnboardingApplication $record): bool => $this->currentUser()->can('process', $record))
            ->action(function (OnboardingApplication $record): void {
                $record->forceFill(['application_pdf_status' => ApplicationPdfStatus::Pending])->save();
                GenerateApplicationPdf::dispatch($record);
                Notification::make()->success()->title('Application PDF queued for regeneration')->send();
                $this->refreshFormData(['application_pdf_status']);
            });
    }

    private function requestInfoAction(): Action
    {
        return Action::make('requestInfo')
            ->label('Request info')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->visible(fn (OnboardingApplication $record): bool => $this->currentUser()->can('process', $record))
            ->schema([
                Textarea::make('notes')->label('What do you need from the applicant?')->required(),
            ])
            ->action(function (array $data, OnboardingApplication $record): void {
                app(RequestApplicationInformationAction::class)->execute($record, $this->currentUser(), $data['notes']);
                Notification::make()->success()->title('Information requested')->send();
            });
    }

    private function recordCgicAction(): Action
    {
        return Action::make('recordCgic')
            ->label('Record CGIC outcome')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('warning')
            ->visible(fn (OnboardingApplication $record): bool => $record->account_type_requested === AccountType::Credit
                && $this->currentUser()->can('recordCgic', $record))
            ->schema([
                Select::make('cgic_status')
                    ->label('CGIC decision')
                    ->options([
                        CgicStatus::Pending->value => 'Pending',
                        CgicStatus::Approved->value => 'Approved',
                        CgicStatus::Declined->value => 'Declined',
                    ])
                    ->required(),
                TextInput::make('cgic_reference')->label('CGIC reference'),
                Textarea::make('cgic_outcome_notes')->label('Outcome notes'),
            ])
            ->action(function (array $data, OnboardingApplication $record): void {
                app(RecordCgicOutcomeAction::class)->execute(
                    $record,
                    $this->currentUser(),
                    CgicStatus::from($data['cgic_status']),
                    $data['cgic_reference'] ?? null,
                    $data['cgic_outcome_notes'] ?? null,
                );
                Notification::make()->success()->title('CGIC outcome recorded')->send();
            });
    }

    private function viewCgicPayloadAction(): Action
    {
        return Action::make('viewCgicPayload')
            ->label('View CGIC submission')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('gray')
            ->visible(fn (OnboardingApplication $record): bool => $record->account_type_requested === AccountType::Credit
                && $this->currentUser()->can('recordCgic', $record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            // Accessing the decrypted packet is audited inside the action.
            ->modalContent(function (OnboardingApplication $record): HtmlString {
                $payload = app(ViewCgicPayloadAction::class)->execute($record, $this->currentUser());

                return new HtmlString(
                    '<pre class="text-xs overflow-auto">'.e(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)).'</pre>'
                );
            });
    }

    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve application')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (OnboardingApplication $record): bool => $this->currentUser()->can('approve', $record))
            ->disabled(fn (OnboardingApplication $record): bool => $this->awaitingCgic($record))
            ->tooltip(fn (OnboardingApplication $record): ?string => $this->awaitingCgic($record) ? 'Awaiting CGIC approval' : null)
            ->action(function (OnboardingApplication $record): void {
                try {
                    app(ApproveOnboardingApplicationAction::class)->execute($record, $this->currentUser());
                } catch (OnboardingDecisionException $e) {
                    Notification::make()->danger()->title('Cannot approve')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Application approved')
                    ->body('The reseller owner account was provisioned and a welcome email queued.')
                    ->send();

                $this->refreshFormData(['status']);
            });
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject application')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (OnboardingApplication $record): bool => $this->currentUser()->can('reject', $record))
            ->schema([
                Textarea::make('rejection_reason')->label('Reason for rejection')->required(),
            ])
            ->action(function (array $data, OnboardingApplication $record): void {
                app(RejectOnboardingApplicationAction::class)->execute($record, $this->currentUser(), $data['rejection_reason']);
                Notification::make()->success()->title('Application rejected')->send();
            });
    }

    private function awaitingCgic(OnboardingApplication $record): bool
    {
        return $record->account_type_requested === AccountType::Credit
            && $record->cgic_status !== CgicStatus::Approved;
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
