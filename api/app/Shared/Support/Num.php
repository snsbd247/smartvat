<?php

namespace App\Shared\Support;

/**
 * Precise fixed-point decimal arithmetic (bcmath) for money and quantities.
 * All ERP monetary/quantity math goes through here to avoid binary float drift.
 * Default working scale is 4 decimal places.
 */
class Num
{
    public const SCALE = 4;

    public static function add(string|float|int $a, string|float|int $b, int $scale = self::SCALE): string
    {
        return bcadd(self::s($a), self::s($b), $scale);
    }

    public static function sub(string|float|int $a, string|float|int $b, int $scale = self::SCALE): string
    {
        return bcsub(self::s($a), self::s($b), $scale);
    }

    public static function mul(string|float|int $a, string|float|int $b, int $scale = self::SCALE): string
    {
        return bcmul(self::s($a), self::s($b), $scale);
    }

    public static function div(string|float|int $a, string|float|int $b, int $scale = self::SCALE): string
    {
        if (self::isZero($b)) {
            return self::zero($scale);
        }

        return bcdiv(self::s($a), self::s($b), $scale);
    }

    /** -1 if a<b, 0 if equal, 1 if a>b (at the given scale). */
    public static function cmp(string|float|int $a, string|float|int $b, int $scale = self::SCALE): int
    {
        return bccomp(self::s($a), self::s($b), $scale);
    }

    public static function eq(string|float|int $a, string|float|int $b, int $scale = self::SCALE): bool
    {
        return self::cmp($a, $b, $scale) === 0;
    }

    public static function isZero(string|float|int $a, int $scale = self::SCALE): bool
    {
        return self::cmp($a, 0, $scale) === 0;
    }

    public static function isNegative(string|float|int $a, int $scale = self::SCALE): bool
    {
        return self::cmp($a, 0, $scale) < 0;
    }

    public static function zero(int $scale = self::SCALE): string
    {
        return number_format(0, $scale, '.', '');
    }

    private static function s(string|float|int $v): string
    {
        return is_float($v) ? number_format($v, 8, '.', '') : (string) $v;
    }
}
