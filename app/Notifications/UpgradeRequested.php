<?php

namespace App\Notifications;

use App\Filament\Ops\Resources\UpgradeRequests\UpgradeRequestResource;
use App\Models\UpgradeRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A heads-up to us when an organizer chooses a paid plan, with what we need for the call back. */
class UpgradeRequested extends Notification
{
    public function __construct(public UpgradeRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->request;
        $u = $r->user;

        return (new MailMessage)
            ->subject("Plan request: {$u->name} · {$r->choiceLabel()}")
            ->line("{$u->name} <{$u->email}> chose {$r->choiceLabel()}".($r->event ? " (event: \"{$r->event->name}\")" : '').'.')
            ->line('Call back on: '.($r->contactPhone() ?: 'no number given'))
            ->lineIf((bool) $r->note, 'They said: '.$r->note)
            ->action('Open in Ops', UpgradeRequestResource::getUrl(panel: 'ops'))
            ->line('Once paid, use "Confirm payment" there to record the period.');
    }
}
