<?php

namespace App\Filament\Ops\Resources\UpgradeRequests\Pages;

use App\Filament\Ops\Resources\UpgradeRequests\UpgradeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListUpgradeRequests extends ListRecords
{
    protected static string $resource = UpgradeRequestResource::class;
}
