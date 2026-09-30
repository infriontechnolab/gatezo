<?php

namespace App\Support;

use App\Models\Event;
use Closure;
use Filament\Facades\Filament;

/**
 * During an admin-panel request Filament stamps the current event onto every new
 * tenant-owned record (gates, stalls, …), overriding any event_id the code set. Code that
 * creates records for a *different* event, like duplicating one, runs inside forEvent().
 */
final class Tenancy
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function forEvent(Event $event, Closure $callback): mixed
    {
        $previous = Filament::getTenant();
        Filament::setTenant($event, isQuiet: true);

        try {
            return $callback();
        } finally {
            Filament::setTenant($previous, isQuiet: true);
        }
    }
}
