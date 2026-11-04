<?php

namespace App\Modules\Accounting\Support;

use App\Support\Contract;

/**
 * Money is integer minor units (KES 1,500.50 is 150050). Amounts arrive as decimal strings and
 * are converted digit by digit, so no float ever touches them.
 */
final class Money
{
    public static function decimals(string $currency): int
    {
        return Contract::CURRENCY_DECIMALS[$currency] ?? 2;
    }

    /**
     * The amount in minor units, or null when it is not a positive amount the currency allows.
     */
    public static function parse(mixed $amount, string $currency): ?int
    {
        if (is_int($amount)) {
            $amount = (string) $amount;
        }
        if (! is_string($amount)) {
            return null;
        }

        $decimals = self::decimals($currency);
        $amount = str_replace([',', ' '], '', trim($amount));
        $pattern = $decimals > 0 ? '/^(\d{1,13})(?:\.(\d{1,'.$decimals.'}))?$/' : '/^(\d{1,13})$/';
        if (! preg_match($pattern, $amount, $match)) {
            return null;
        }

        $minor = (int) ($match[1].str_pad($match[2] ?? '', $decimals, '0'));

        return $minor > 0 && $minor <= Contract::ACCOUNTING_AMOUNT_MAX ? $minor : null;
    }

    /**
     * Opening balances may be zero or negative (a card that is already owed).
     */
    public static function parseSigned(mixed $amount, string $currency): ?int
    {
        if (is_int($amount)) {
            $amount = (string) $amount;
        }
        if (! is_string($amount)) {
            return null;
        }

        $amount = trim($amount);
        $negative = str_starts_with($amount, '-');
        $digits = ltrim($negative ? substr($amount, 1) : $amount);
        if (preg_match('/^0+(\.0+)?$/', $digits)) {
            return 0;
        }

        $minor = self::parse($digits, $currency);

        return $minor === null ? null : ($negative ? -$minor : $minor);
    }

    public static function format(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);
        $digits = str_pad((string) abs($minor), $decimals + 1, '0', STR_PAD_LEFT);
        $whole = $decimals > 0 ? substr($digits, 0, -$decimals) : $digits;
        $fraction = $decimals > 0 ? '.'.substr($digits, -$decimals) : '';

        return ($minor < 0 ? '-' : '').$whole.$fraction;
    }
}
