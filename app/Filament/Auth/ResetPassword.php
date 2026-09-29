<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Schemas\Components\Component;

/** Filament's "set a new password" page, with placeholders. */
class ResetPassword extends BaseResetPassword
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->placeholder('you@example.com');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->placeholder('At least 8 characters');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()->placeholder('Same again');
    }
}
