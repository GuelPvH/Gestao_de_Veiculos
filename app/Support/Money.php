<?php

namespace App\Support;

use GMP;
use InvalidArgumentException;

final class Money
{
    private const LIMIT_CENTS = '9999999999999';

    public static function lineTotal(string $quantity, string $unitValue): string
    {
        $thousandthsOfCent = gmp_mul(self::units($quantity, 3), self::units($unitValue, 2));

        return self::format(gmp_div_q(gmp_add($thousandthsOfCent, 500), 1000));
    }

    /** @param iterable<string> $totals */
    public static function sum(iterable $totals): string
    {
        $cents = gmp_init(0);
        foreach ($totals as $total) {
            $cents = gmp_add($cents, self::units($total, 2));
        }

        return self::format($cents);
    }

    public static function difference(string $minuend, string $subtrahend): string
    {
        return self::format(gmp_sub(self::units($minuend, 2), self::units($subtrahend, 2)));
    }

    public static function exceedsLimit(string $amount): bool
    {
        return gmp_cmp(self::units($amount, 2), self::LIMIT_CENTS) > 0;
    }

    private static function units(string $decimal, int $scale): GMP
    {
        if (preg_match('/^(\d{1,12})(?:\.(\d{1,'.$scale.'}))?$/', $decimal, $parts) !== 1) {
            throw new InvalidArgumentException('Valor decimal inválido.');
        }

        return gmp_init($parts[1].str_pad($parts[2] ?? '', $scale, '0'), 10);
    }

    private static function format(GMP $cents): string
    {
        $digits = str_pad(gmp_strval(gmp_abs($cents)), 3, '0', STR_PAD_LEFT);

        return (gmp_sign($cents) < 0 ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
