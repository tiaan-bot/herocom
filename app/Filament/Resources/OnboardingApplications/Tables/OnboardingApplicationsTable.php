<?php

namespace App\Filament\Resources\OnboardingApplications\Tables;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationStatus;
use App\Domain\Onboarding\Enums\CgicStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnboardingApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.legal_name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account_type_requested')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (AccountType $state): string => $state === AccountType::Credit ? 'Credit' : 'COD')
                    ->color(fn (AccountType $state): string => $state === AccountType::Credit ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => (string) str($state->value)->headline())
                    ->color(fn (ApplicationStatus $state): string => match ($state) {
                        ApplicationStatus::Submitted, ApplicationStatus::UnderReview => 'info',
                        ApplicationStatus::InfoRequested => 'warning',
                        ApplicationStatus::Approved => 'success',
                        ApplicationStatus::Rejected => 'danger',
                    }),
                TextColumn::make('cgic_status')
                    ->label('CGIC')
                    ->badge()
                    ->formatStateUsing(fn (CgicStatus $state): string => $state === CgicStatus::NotRequired ? '—' : (string) str($state->value)->headline())
                    ->color(fn (CgicStatus $state): string => match ($state) {
                        CgicStatus::NotRequired => 'gray',
                        CgicStatus::Pending => 'warning',
                        CgicStatus::Approved => 'success',
                        CgicStatus::Declined => 'danger',
                    }),
                TextColumn::make('contact_name')
                    ->searchable(),
                TextColumn::make('contact_email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ApplicationStatus::class),
                SelectFilter::make('account_type_requested')->label('Account type')->options(AccountType::class),
                SelectFilter::make('cgic_status')->label('CGIC status')->options(CgicStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            // Open/actionable applications first, newest submissions next.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE status
                    WHEN 'submitted' THEN 0
                    WHEN 'under_review' THEN 1
                    WHEN 'info_requested' THEN 2
                    WHEN 'approved' THEN 3
                    WHEN 'rejected' THEN 4
                    ELSE 5 END")
                ->orderByDesc('submitted_at'));
    }
}
