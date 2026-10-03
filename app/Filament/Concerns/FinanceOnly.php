<?php

namespace App\Filament\Concerns;

/** Restricts a resource or page to finance_admin and super_admin. */
trait FinanceOnly
{
    public static function isFinanceUser(): bool
    {
        $user = auth()->user();

        return $user !== null && method_exists($user, 'isFinanceAdmin') && $user->isFinanceAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::isFinanceUser();
    }

    public static function canAccess(): bool
    {
        return static::isFinanceUser();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isFinanceUser();
    }
}
