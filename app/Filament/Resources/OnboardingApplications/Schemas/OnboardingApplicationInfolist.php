<?php

namespace App\Filament\Resources\OnboardingApplications\Schemas;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationPdfStatus;
use App\Domain\Onboarding\Models\OnboardingApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OnboardingApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('account_type_requested')->label('Account type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('contact_name'),
                        TextEntry::make('contact_email'),
                        TextEntry::make('contact_phone'),
                        TextEntry::make('submitted_at')->dateTime(),
                    ]),

                Section::make('Company')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('company.legal_name')->label('Legal name'),
                        TextEntry::make('company.trading_name')->label('Trading name')->placeholder('—'),
                        TextEntry::make('company.entity_type')->label('Entity type')->badge(),
                        TextEntry::make('company.registration_number')->label('Registration no.')->placeholder('—'),
                        TextEntry::make('company.date_of_registration')->label('Date of registration')->date()->placeholder('—'),
                        TextEntry::make('company.vat_number')->label('VAT no.')->placeholder('—'),
                        TextEntry::make('company.nature_of_business')->label('Nature of business')->placeholder('—'),
                        TextEntry::make('company.telephone')->label('Telephone')->placeholder('—'),
                        TextEntry::make('company.fax')->label('Fax')->placeholder('—'),
                        TextEntry::make('company.postal_address_line1')->label('Postal address')
                            ->formatStateUsing(fn (OnboardingApplication $record): string => collect([
                                $record->company->postal_address_line1,
                                $record->company->postal_province,
                                $record->company->postal_postal_code,
                            ])->filter()->join(', ') ?: '—')
                            ->columnSpanFull(),
                        TextEntry::make('company.address_line1')->label('Address')
                            ->formatStateUsing(fn (OnboardingApplication $record): string => collect([
                                $record->company->address_line1,
                                $record->company->address_line2,
                                $record->company->city,
                                $record->company->province,
                                $record->company->postal_code,
                                $record->company->country_code,
                            ])->filter()->join(', '))
                            ->columnSpanFull(),
                    ]),

                Section::make('Credit request')
                    ->columns(3)
                    ->visible(fn (OnboardingApplication $record): bool => $record->account_type_requested === AccountType::Credit)
                    ->schema([
                        TextEntry::make('credit_limit_requested')->label('Limit requested')->money(fn (OnboardingApplication $record): string => $record->credit_limit_requested_currency),
                        TextEntry::make('credit_terms_requested_days')->label('Terms (days)')->suffix(' days'),
                        TextEntry::make('annual_turnover_band')->label('Turnover band')->badge(),
                        TextEntry::make('account_contact_name')->label('Account contact')->placeholder('—'),
                        TextEntry::make('account_contact_email')->label('Account contact email')->placeholder('—'),
                        TextEntry::make('account_contact_phone')->label('Account contact phone')->placeholder('—'),
                    ]),

                Section::make('CGIC')
                    ->columns(2)
                    ->visible(fn (OnboardingApplication $record): bool => $record->account_type_requested === AccountType::Credit)
                    ->schema([
                        TextEntry::make('cgic_status')->label('CGIC status')->badge(),
                        TextEntry::make('cgic_reference')->label('Reference')->placeholder('—'),
                        TextEntry::make('cgic_outcome_notes')->label('Outcome notes')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('cgic_decided_at')->label('Decided at')->dateTime()->placeholder('—'),
                        TextEntry::make('cgicDecidedBy.name')->label('Decided by')->placeholder('—'),
                    ]),

                Section::make('Consent')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('terms_accepted_at')->label('T&Cs accepted')->dateTime()->placeholder('—'),
                        TextEntry::make('terms_version')->label('T&Cs version')->placeholder('—'),
                        TextEntry::make('popia_consent_at')->label('POPIA consent')->dateTime()->placeholder('—'),
                        TextEntry::make('credit_enquiry_consent_at')->label('Credit enquiry consent')->dateTime()->placeholder('—'),
                    ]),

                Section::make('Signature & form')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('signed_by_name')->label('Signed by')->placeholder('—'),
                        TextEntry::make('signed_by_capacity')->label('Capacity')->placeholder('—'),
                        TextEntry::make('signed_at')->label('Signed at')->dateTime()->placeholder('—'),
                        TextEntry::make('application_pdf_status')
                            ->label('Application PDF')
                            ->badge()
                            ->color(fn ($state): string => match ($state) {
                                ApplicationPdfStatus::Generated => 'success',
                                ApplicationPdfStatus::Failed => 'danger',
                                default => 'warning',
                            }),
                    ]),

                Section::make('Decision trail')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
                        TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('decision_notes')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
