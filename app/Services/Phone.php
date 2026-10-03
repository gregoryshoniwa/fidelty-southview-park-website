<?php

namespace App\Services;

final class Phone
{
    /** Normalise Zimbabwean numbers to E.164 (+263...). Returns null when invalid. */
    public static function normalise(?string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '0')) {
            $d = '263'.substr($d, 1);
        }
        if (strlen($d) === 9 && str_starts_with($d, '7')) {
            $d = '263'.$d;
        }
        if (! preg_match('/^2637[1378]\d{7}$/', $d)) {
            return null;
        }

        return '+'.$d;
    }

    public static function mask(string $e164): string
    {
        return substr($e164, 0, 4).' •• ••• '.substr($e164, -3);
    }
}
