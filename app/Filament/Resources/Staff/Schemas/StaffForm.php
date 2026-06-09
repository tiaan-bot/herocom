<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Domain\Identity\Roles;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                // Unique among live accounts only (a soft-deleted email may be reused).
                ->unique(
                    table: 'users',
                    column: 'email',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'),
                ),

            Select::make('roles')
                ->label('Internal roles')
                ->multiple()
                ->required()
                ->options(Roles::internalOptions())
                ->helperText('Only internal staff roles. Reseller tiers are managed elsewhere and can never be assigned here.'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Deactivating blocks sign-in and admin access.')
                ->visible(fn (string $operation): bool => $operation === 'edit'),
        ]);
    }
}
