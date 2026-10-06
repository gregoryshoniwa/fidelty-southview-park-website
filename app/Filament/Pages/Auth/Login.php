<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Committee sign-in, worded and styled like the partner portal's two-step sign-in. */
class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? new HtmlString('<span class="sv-step">Step 2 of 2</span>Enter your code')
            : new HtmlString('<span class="sv-step">Step 1 of 2</span>Sign in');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? 'Open your authenticator app and enter the 6-digit code for Southview Park Committee.'
            : 'Use the email address and password the committee registered for you.';
    }

    /** Email and password only: no "Remember me", committee sessions end when the browser closes. */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->placeholder('name@example.com');
    }

    /** Eye button inside the box with no divider, like the partner portal. */
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->inlineSuffix();
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label('Continue')->icon(Heroicon::OutlinedArrowRightEndOnRectangle);
    }

    protected function getMultiFactorAuthenticateFormAction(): Action
    {
        return parent::getMultiFactorAuthenticateFormAction()->label('Sign in')->icon(Heroicon::OutlinedShieldCheck);
    }
}
