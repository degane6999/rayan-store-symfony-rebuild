<?php

declare(strict_types=1);

namespace App\Util;

/**
 * All monetary amounts are handled internally as integer cents to avoid
 * floating point rounding errors. DECIMAL(10,2) strings are the storage/display format.
 */
final class Money
{
    public static function toCents(string $decimal): int
    {
        $normalized = str_replace(',', '.', trim($decimal));
        if (!is_numeric($normalized)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid monetary amount.', $decimal));
        }

        return (int) round(((float) $normalized) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function multiply(string $unitPriceDecimal, int $quantity): int
    {
        return self::toCents($unitPriceDecimal) * $quantity;
    }

    public static function addCents(int ...$cents): int
    {
        return array_sum($cents);
    }
}
