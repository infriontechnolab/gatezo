<?php

namespace App\Filament\Resources\Checkins\Pages;

use App\Filament\Resources\Checkins\CheckinResource;
use Filament\Resources\Pages\ListRecords;

class ListCheckins extends ListRecords
{
    protected static string $resource = CheckinResource::class;
}
