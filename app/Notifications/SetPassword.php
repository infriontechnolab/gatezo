<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * First-login mail for an organizer we created by hand. Same token flow as a password
 * reset, different words: they never had a password to reset.
 */
class SetPassword extends ResetPassword
{
    public function __construct(string $token, public string $eventName = '')
    {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Your Gatezo login')
            ->greeting("Hi {$notifiable->name},")
            ->line($this->eventName !== ''
                ? "Your Gatezo account is ready and \"{$this->eventName}\" is set up for you."
                : 'Your Gatezo account is ready.')
            ->line('Choose a password to sign in:')
            ->action('Set my password', $this->url)
            ->line("This link works for {$minutes} minutes. If it has expired, reply to this mail or use \"Forgot password\" on the sign-in page.")
            ->salutation('Gatezo, by Infrion Technolab');
    }
}
