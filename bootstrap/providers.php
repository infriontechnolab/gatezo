<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\OpsPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    OpsPanelProvider::class,
];
