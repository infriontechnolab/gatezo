<?php

namespace App\Filament\Auth;

use App\Notifications\NewSignup;
use App\Rules\PhoneNumber;
use App\Support\Phone;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\HtmlString;
use SensitiveParameter;

/**
 * Self-serve organizer sign-up. Lands on the free plan (config/gatezo.php `plans`) and,
 * having no event yet, is sent straight to "Create event" by Filament's tenancy.
 * Phone is required: the sign-up *is* the lead, and WhatsApp is how we follow up.
 */
class Register extends BaseRegister
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.auth.split';

    /** "Start your own event" in the demo banner: drop the shared demo session first. */
    public function mount(): void
    {
        if (auth()->user()?->email === config('gatezo.demo.email')) {
            auth()->logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        parent::mount();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Start your event';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('Free to start, kit printed in five minutes. Already have a login? '.$this->loginAction->toHtml());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent()->placeholder('Bhavesh Patel'),
            $this->getEmailFormComponent()->placeholder('you@example.com'),
            $this->getPhoneFormComponent(),
            $this->getPasswordFormComponent()->placeholder('At least 8 characters'),
            $this->getPasswordConfirmationFormComponent()->placeholder('Same again'),
        ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('WhatsApp number')
            ->tel()
            ->required()
            ->maxLength(25)
            ->rule(new PhoneNumber)
            ->placeholder('10-digit mobile')
            ->helperText('So we can help you on the day. Never shown to attendees.');
    }

    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        $data['phone'] = Phone::normalise($data['phone']);
        $data['plan'] = 'free';

        return $data;
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = parent::handleRegistration($data);

        if ($to = config('gatezo.signup_notify')) {
            Notification::route('mail', $to)->notify(new NewSignup($user));
        }

        return $user;
    }
}
