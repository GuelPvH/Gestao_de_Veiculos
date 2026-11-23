<?php

namespace App\Support;

final class BrandLogo
{
    /** @var array{logotipo: ?string, subtitulo: ?string}|null */
    private static ?array $paths = null;

    /**
     * @return array{logotipo: ?string, subtitulo: ?string}
     */
    public static function horizontalPaths(): array
    {
        if (self::$paths !== null) {
            return self::$paths;
        }

        $caminho = public_path('images/logo/horizontal-claro.svg');
        $conteudo = is_file($caminho) ? file_get_contents($caminho) : false;
        if (! is_string($conteudo) || $conteudo === '') {
            return self::$paths = ['logotipo' => null, 'subtitulo' => null];
        }

        $logotipo = preg_match('/<g id="logotipo"[^>]*>.*?<path[^>]*\bd="([^"]+)"/s', $conteudo, $matchLogo) === 1
            ? $matchLogo[1]
            : null;
        $subtitulo = preg_match('/<g id="subtitulo"[^>]*>.*?<path[^>]*\bd="([^"]+)"/s', $conteudo, $matchSub) === 1
            ? $matchSub[1]
            : null;

        return self::$paths = ['logotipo' => $logotipo, 'subtitulo' => $subtitulo];
    }
}
