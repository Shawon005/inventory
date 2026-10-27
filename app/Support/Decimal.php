<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Decimal strings only in posting code; never route money through a float. */
final class Decimal
{
    public static function value($value, int $scale = 4): string
    {
        if ((! is_string($value) && ! is_int($value)) ||
            ! preg_match('/^-?\d{1,18}(?:\.\d{1,12})?$/D', (string) $value)) {
            throw ValidationException::withMessages(['quantity' => 'Use a decimal number without separators or scientific notation.']);
        }

        $rounded = self::round((string) $value, $scale);
        if (bccomp($rounded, '999999999999999999.9999', 4) > 0 || bccomp($rounded, '-999999999999999999.9999', 4) < 0) {
            throw ValidationException::withMessages(['quantity' => 'The number exceeds the supported precision.']);
        }

        return $rounded;
    }

    public static function round(string $value, int $scale = 4): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';
        return bcadd($value, bccomp($value, '0', $scale + 8) < 0 ? '-'.$half : $half, $scale);
    }

    public static function add(string $a, string $b): string { return self::value(bcadd($a, $b, 4)); }
    public static function sub(string $a, string $b): string { return self::value(bcsub($a, $b, 4)); }
    public static function mul(string $a, string $b): string { return self::value(bcmul($a, $b, 12)); }
    public static function div(string $a, string $b): string
    {
        self::value($b);
        if (bccomp($b, '0', 12) <= 0) { throw ValidationException::withMessages(['quantity' => 'The divisor must be greater than zero.']); }
        return self::value(bcdiv($a, $b, 12));
    }
    public static function cmp(string $a, string $b): int { return bccomp($a, $b, 4); }
    public static function positive(string $value, string $field = 'quantity', bool $zero = false): string
    {
        $value = self::value($value);
        if (self::cmp($value, '0') < ($zero ? 0 : 1)) {
            throw ValidationException::withMessages([$field => $zero ? 'Must be zero or greater.' : 'Must be greater than zero.']);
        }
        return $value;
    }

    /** Compatibility at the boundary of the existing float-based utilities. */
    public static function legacy($value): string
    {
        return self::value(is_float($value) ? number_format($value, 4, '.', '') : (string) $value);
    }

    public static function localized($value, $currency = null): string
    {
        if (is_float($value) || is_int($value)) { return self::legacy($value); }
        $thousand = is_object($currency) ? ($currency->thousand_separator ?? '') : (session('currency.thousand_separator') ?? '');
        $decimal = is_object($currency) ? ($currency->decimal_separator ?? '.') : (session('currency.decimal_separator') ?? '.');
        $value = (string) $value;
        if ($thousand !== '') { $value = str_replace($thousand, '', $value); }
        if ($decimal !== '' && $decimal !== '.') { $value = str_replace($decimal, '.', $value); }
        return self::value($value);
    }
}
