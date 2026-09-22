<?php

namespace App\Notifications;

use App\Models\UpgradeRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One line to us when an organizer asks for Pro, so it's handled even if the chat never starts. */
class UpgradeRequested extends Notification
{
    public function __construct(public UpgradeRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $u = $this->request->user;
        $digits = (string) $u->phone;

        return (new MailMessage)
            ->subject("Upgrade request: {$u->name}".($this->request->event ? " · {$this->request->event->name}" : ''))
            ->line("{$u->name} <{$u->email}> wants Pro".($this->request->event ? " for \"{$this->request->event->name}\"" : '').'.')
            ->line('Phone: '.($u->phone ?: 'not given'))
            ->lineIf((bool) $this->request->note, 'They said: '.$this->request->note)
            ->action('Say hello on WhatsApp', 'https://wa.me/'.(strlen($digits) === 10 ? '91' : '').$digits)
            ->line('Resolve it in Ops → Upgrade requests, or: php artisan gatezo:plan '.$u->email.' pro');
    }
}
