<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Split-screen login: brand panel on the left, Filament's stock form on the right.
 * The form, validation, rate limiting and MFA all stay Filament's; only the shell changes.
 * Register shares the same view.
 */
class Login extends BaseLogin
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected string $view = 'filament.auth.split';

    public function authenticate(): ?LoginResponse
    {
        $email = strtolower(trim((string) ($this->data['email'] ?? '')));
        if ($email !== '' && User::where('email', $email)->where('is_admin', true)->exists()) {
            $this->redirect(Filament::getPanel('ops')->getLoginUrl());

            return null;
        }

        return parent::authenticate();
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->placeholder('you@example.com');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->placeholder('Your password');
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Welcome back';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return new HtmlString('Sign in to run your event. New here? '.$this->registerAction->label('Start your event, free')->toHtml());
    }
}
