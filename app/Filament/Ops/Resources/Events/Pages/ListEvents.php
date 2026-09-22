<?php

namespace App\Filament\Ops\Resources\Events\Pages;

use App\Filament\Ops\Resources\Events\EventResource;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;
}
