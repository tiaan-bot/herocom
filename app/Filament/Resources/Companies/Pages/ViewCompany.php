<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Onboarding\Actions\SetCompanyCreditAction;
use App\Domain\Onboarding\Enums\CreditTerms;
use App\Domain\Onboarding\Models\Company;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->setCreditAction(),
        ];
    }

    private function setCreditAction(): Action
    {
        return Action::make('setCredit')
            ->label('Set credit terms')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (Company $record): bool => $this->currentUser()->can('manageCredit', $record))
            ->fillForm(fn (Company $record): array => [
                'credit_terms' => $record->credit_terms->value,
                'credit_limit' => $record->credit_limit,
                'credit_limit_currency' => $record->credit_limit_currency,
                'credit_terms_days' => $record->credit_terms_days,
            ])
            ->schema([
                Select::make('credit_terms')
                    ->options([
                        CreditTerms::EftUpfront->value => 'EFT upfront (COD)',
                        CreditTerms::OnAccount->value => 'On account (credit)',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('credit_limit')
                    ->numeric()
                    ->minValue(0)
                    ->visible(fn (Get $get): bool => $get('credit_terms') === CreditTerms::OnAccount->value),
                TextInput::make('credit_limit_currency')
                    ->default('ZAR')
                    ->maxLength(3)
                    ->visible(fn (Get $get): bool => $get('credit_terms') === CreditTerms::OnAccount->value),
                Select::make('credit_terms_days')
                    ->label('Terms (days)')
                    ->options([7 => '7 days', 15 => '15 days', 30 => '30 days'])
                    ->visible(fn (Get $get): bool => $get('credit_terms') === CreditTerms::OnAccount->value),
            ])
            ->action(function (array $data, Company $record): void {
                app(SetCompanyCreditAction::class)->execute(
                    $record,
                    $this->currentUser(),
                    CreditTerms::from($data['credit_terms']),
                    isset($data['credit_limit']) ? (float) $data['credit_limit'] : null,
                    isset($data['credit_terms_days']) ? (int) $data['credit_terms_days'] : null,
                );
                Notification::make()->success()->title('Credit terms updated')->send();
            });
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
