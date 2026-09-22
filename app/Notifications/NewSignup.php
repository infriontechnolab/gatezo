<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One line to us for every self-serve sign-up, so the lead is not lost in a table. */
class NewSignup extends Notification
{
    public function __construct(public User $user) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Phones are stored normalised (10 digits for India); wa.me wants the country code in front. */
    private function whatsappNumber(): string
    {
        $digits = (string) $this->user->phone;

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Gatezo sign-up: {$this->user->name}")
            ->line("{$this->user->name} <{$this->user->email}> just signed up on the free plan.")
            ->line('Phone: '.($this->user->phone ?: 'not given'))
            ->action('Say hello on WhatsApp', 'https://wa.me/'.$this->whatsappNumber())
            ->line('Upgrade them later with: php artisan gatezo:plan '.$this->user->email.' pro');
    }
}
