<?php

namespace App\Filament\Ops\Resources\Subscriptions\Pages;

use App\Filament\Ops\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscriptions extends ManageRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Record paid period')
                ->mutateDataUsing(fn (array $data) => [...$data, 'created_by' => auth()->id()]),
        ];
    }
}
