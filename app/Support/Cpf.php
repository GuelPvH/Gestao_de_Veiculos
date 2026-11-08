<?php

namespace App\Support;

class Cpf
{
    public static function normalize(string $input): ?string
    {
        $input = trim($input);
        if (! preg_match('/\A(?:[0-9]{11}|[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2})\z/', $input)) {
            return null;
        }
        $digits = str_replace(['.', '-'], '', $input);
        if ($digits === str_repeat($digits[0], 11)) {
            return null;
        }
        for ($position = 9; $position <= 10; $position++) {
            $total = 0;
            for ($index = 0; $index < $position; $index++) {
                $total += (int) $digits[$index] * ($position + 1 - $index);
            }
            if ((int) $digits[$position] !== (10 * $total % 11) % 10) {
                return null;
            }
        }

        return $digits;
    }

    /** @return array{string, string} */
    public static function storedIdentifiers(string $cpf): array
    {
        return [$cpf, substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9)];
    }
}
