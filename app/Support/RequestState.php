<?php

namespace App\Support;

use Illuminate\Http\Request;
use stdClass;

final class RequestState
{
    public static function record(Request $request, string $name): ?stdClass
    {
        $value = $request->attributes->get($name);
        abort_unless($value === null || $value instanceof stdClass, 503);

        return $value;
    }

    public static function requireRecord(Request $request, string $name): stdClass
    {
        $value = self::record($request, $name);
        abort_unless($value !== null, 401);

        return $value;
    }
}
