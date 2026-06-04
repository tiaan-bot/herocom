<?php

namespace App\Filament\Resources\OnboardingApplications\Pages;

use App\Filament\Resources\OnboardingApplications\OnboardingApplicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOnboardingApplications extends ListRecords
{
    protected static string $resource = OnboardingApplicationResource::class;

    /**
     * No create action — applications arrive via the public submission flow.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
