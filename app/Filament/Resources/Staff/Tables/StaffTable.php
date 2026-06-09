<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Domain\Identity\Actions\SetStaffActiveStatusAction;
use App\Domain\Identity\Enums\StaffStatus;
use App\Domain\Identity\Exceptions\StaffProtectionException;
use App\Domain\Identity\Roles;
use App\Domain\Identity\StaffProtection;
use App\Models\User;
use App\Notifications\StaffInviteNotification;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => (string) str($state)->headline()),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->staffStatus()->label())
                    ->color(fn (User $record): string => $record->staffStatus()->color()),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', Roles::INTERNAL))
                    ->multiple(),
                SelectFilter::make('status')
                    ->options([
                        StaffStatus::Active->value => 'Active',
                        StaffStatus::Inactive->value => 'Inactive',
                        StaffStatus::PendingInvite->value => 'Pending invite',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            StaffStatus::Active->value => $query->where('is_active', true)->whereNotNull('password_set_at'),
                            StaffStatus::Inactive->value => $query->where('is_active', false),
                            StaffStatus::PendingInvite->value => $query->where('is_active', true)->whereNull('password_set_at'),
                            default => $query,
                        };
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (User $record): bool => ! $record->trashed()),

                Action::make('resendInvite')
                    ->label('Resend invite')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->visible(fn (User $record): bool => ! $record->trashed() && $record->staffStatus() === StaffStatus::PendingInvite)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->notify(new StaffInviteNotification);
                        Notification::make()->success()->title('Invite re-sent')->send();
                    }),

                Action::make('toggleActive')
                    ->label(fn (User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record): Heroicon => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->visible(fn (User $record): bool => ! $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        try {
                            app(SetStaffActiveStatusAction::class)->execute($record, ! $record->is_active, self::actor());
                        } catch (StaffProtectionException $e) {
                            Notification::make()->danger()->title('Action blocked')->body($e->getMessage())->send();

                            return;
                        }
                        Notification::make()->success()->title($record->is_active ? 'Staff activated' : 'Staff deactivated')->send();
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (User $record): bool => ! $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        try {
                            app(StaffProtection::class)->assertCanDelete($record, self::actor());
                        } catch (StaffProtectionException $e) {
                            Notification::make()->danger()->title('Action blocked')->body($e->getMessage())->send();

                            return;
                        }
                        $record->delete();
                        Notification::make()->success()->title('Staff deleted')->send();
                    }),

                Action::make('restore')
                    ->label('Restore')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->restore();
                        Notification::make()->success()->title('Staff restored')->send();
                    }),
            ]);
    }

    private static function actor(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
