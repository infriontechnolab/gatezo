<?php

namespace App\Filament\Ops\Resources\Plans\Pages;

use App\Filament\Ops\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New plan'),
        ];
    }
}
