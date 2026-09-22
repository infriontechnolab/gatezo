<?php

namespace App\Filament\Ops\Widgets;

use App\Filament\Ops\Resources\UpgradeRequests\UpgradeRequestResource;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\UpgradeRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The business at a glance: who signed up, who pays, what's coming up. */
class Overview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $organizers = User::organizers();
        $week = (clone $organizers)->where('created_at', '>=', now()->subDays(7))->count();
        $month = (clone $organizers)->where('created_at', '>=', now()->subDays(30))->count();
        $pro = (clone $organizers)->where('plan', 'pro')->count();
        $total = (clone $organizers)->count();

        $upcoming = Event::whereBetween('starts_at', [now(), now()->addDays(30)])->count();
        $live = Event::where('starts_at', '<=', now())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->whereHas('checkins', fn ($q) => $q->where('scanned_at', '>=', now()->subHours(6)))->count();

        // Sign-ups per day for the last 14 days, for the sparkline.
        $spark = collect(range(13, 0))->map(fn (int $i) => (clone $organizers)->whereDate('created_at', now()->subDays($i)->toDateString())->count());

        $pending = UpgradeRequest::where('status', 'pending')->count();

        return [
            Stat::make('Upgrade requests', number_format($pending))
                ->description($pending ? 'waiting for a reply' : 'nothing pending')
                ->color($pending ? 'warning' : 'gray')
                ->url(UpgradeRequestResource::getUrl()),
            Stat::make('Organizers', number_format($total))
                ->description("{$week} this week · {$month} this month")
                ->chart($spark->all())
                ->color($week > 0 ? 'success' : 'gray'),
            Stat::make('On Pro', number_format($pro))
                ->description($total ? round($pro / $total * 100).'% of organizers' : 'no organizers yet')
                ->color('primary'),
            Stat::make('Events', number_format(Event::count()))
                ->description("{$upcoming} in the next 30 days · {$live} scanning right now")
                ->color($live ? 'success' : 'gray'),
            Stat::make('Registrations', number_format(Attendee::count()))
                ->description(number_format(Attendee::where('created_at', '>=', now()->subDays(7))->count()).' this week'),
        ];
    }
}
