<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;

/** Staff sign-in for /ops: Filament's stock login, with placeholders. */
class OpsLogin extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->placeholder('you@gatezo.in');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->placeholder('Your password');
    }
}
