<?php

namespace App\Providers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Normally links follow the request host. APP_FORCE_URL=true pins them to APP_URL
        // instead, e.g. to render the print kit with the public domain from a local server.
        if (config('app.force_url')) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Filament's own calendar instead of the browser's native date input, everywhere
        // (DatePicker extends DateTimePicker, so this covers both).
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
            ->native(false)
            ->displayFormat(fn (DateTimePicker $c) => $c->hasTime() ? 'D j M Y, g:i A' : 'D j M Y')
            ->firstDayOfWeek(1));

        // Same for dropdowns: Filament's styled list, not the browser's, in forms and table filters.
        Select::configureUsing(fn (Select $select) => $select->native(false));
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->native(false));
    }
}
