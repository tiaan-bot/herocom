<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Domain\Identity\Actions\InviteStaffMemberAction;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // No password field: the invitee is created Pending (unusable password,
        // password_set_at null) and emailed a signed set-password link.
        return app(InviteStaffMemberAction::class)->execute(
            (string) $data['name'],
            (string) $data['email'],
            $data['roles'] ?? [],
        );
    }
}
