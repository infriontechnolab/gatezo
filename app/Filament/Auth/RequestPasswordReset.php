<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Schemas\Components\Component;

/** Filament's "forgot password" page, with a placeholder on the email field. */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->placeholder('you@example.com');
    }
}
