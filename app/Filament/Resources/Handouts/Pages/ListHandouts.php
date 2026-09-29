<?php

namespace App\Filament\Resources\Handouts\Pages;

use App\Filament\Resources\Handouts\HandoutResource;
use Filament\Resources\Pages\ListRecords;

class ListHandouts extends ListRecords
{
    protected static string $resource = HandoutResource::class;
}
