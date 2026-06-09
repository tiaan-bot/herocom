<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Domain\Identity\Actions\SetStaffActiveStatusAction;
use App\Domain\Identity\Actions\SyncStaffRolesAction;
use App\Domain\Identity\Exceptions\StaffProtectionException;
use App\Domain\Identity\Roles;
use App\Domain\Identity\StaffProtection;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record;
        assert($user instanceof User);
        $data['roles'] = $user->getRoleNames()->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof User);
        $actor = auth()->user();
        assert($actor instanceof User);

        $roles = array_values(array_filter($data['roles'] ?? [], Roles::isInternal(...)));
        $active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $record->is_active;

        // Validate the self-protection guards up front so nothing is partially saved.
        $protection = app(StaffProtection::class);
        try {
            $protection->assertRolesAllowed($record, $actor, $roles);
            if (! $active) {
                $protection->assertCanDeactivate($record, $actor);
            }
        } catch (StaffProtectionException $e) {
            Notification::make()->danger()->title('Action blocked')->body($e->getMessage())->send();
            throw new Halt;
        }

        $record->fill(['name' => $data['name'], 'email' => $data['email']])->save();
        app(SyncStaffRolesAction::class)->execute($record, $roles, $actor);
        app(SetStaffActiveStatusAction::class)->execute($record, $active, $actor);

        return $record->refresh();
    }
}
