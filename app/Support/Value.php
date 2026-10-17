<?php

namespace App\Support;

use Stringable;
use UnexpectedValueException;

/** Normalize scalar values at HTTP, configuration and database boundaries. */
final class Value
{
    public static function integer(mixed $value): int
    {
        if (! is_scalar($value) && $value !== null) {
            throw new UnexpectedValueException('Expected a scalar integer value.');
        }

        return (int) $value;
    }

    public static function number(mixed $value): float
    {
        if (! is_scalar($value) && $value !== null) {
            throw new UnexpectedValueException('Expected a scalar numeric value.');
        }

        return (float) $value;
    }

    public static function string(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null && ! $value instanceof Stringable) {
            throw new UnexpectedValueException('Expected a scalar string value.');
        }

        return (string) $value;
    }

    public static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    public static function date(mixed $value): \DateTimeInterface|int|float|string|null
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value) || $value instanceof \DateTimeInterface) {
            return $value;
        }

        throw new UnexpectedValueException('Expected a date value.');
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        $result = [];
        foreach (self::array($value) as $key => $item) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Expected named data fields.');
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        $result = [];
        foreach (self::array($value) as $item) {
            if (! is_string($item)) {
                throw new UnexpectedValueException('Expected a list of strings.');
            }
            $result[] = $item;
        }

        return $result;
    }

    /** @return array<array-key, mixed> */
    public static function array(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UnexpectedValueException('Expected an array value.');
        }

        return $value;
    }
}
