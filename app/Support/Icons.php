<?php

namespace App\Support;

final class Icons
{
    private const ALLOWED = [
        'breadcrumb-home',
        'breadcrumb-chevron',
        'pagination-previous',
        'pagination-next',
        'menu',
        'home',
        'clipboard-list',
        'calendar-days',
        'car-front',
        'map-pinned',
        'history',
        'route',
        'file-text',
        'life-buoy',
        'moon',
        'bell',
        'chevron-right',
    ];

    /** @var array<string, string> */
    private static array $cache = [];

    public static function svg(string $name): ?string
    {
        if (! in_array($name, self::ALLOWED, true)) {
            return null;
        }
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $content = file_get_contents(public_path('icons/'.$name.'.svg'));
        if (! is_string($content)) {
            return null;
        }

        return self::$cache[$name] = $content;
    }
}
