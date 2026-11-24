<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

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
        $mtime = is_file($caminho) ? filemtime($caminho) : false;
        if ($mtime === false) {
            return self::$paths = ['logotipo' => null, 'subtitulo' => null];
        }

        $chave = 'fleet:brand-logo:horizontal:'.$mtime;
        try {
            $cached = Cache::store('file')->get($chave);
            if (is_array($cached) && array_key_exists('logotipo', $cached) && array_key_exists('subtitulo', $cached)) {
                /** @var array{logotipo: ?string, subtitulo: ?string} $cached */
                return self::$paths = $cached;
            }
        } catch (Throwable) {
            // Segue para leitura direta se o cache em arquivo não estiver disponível.
        }

        $conteudo = file_get_contents($caminho);
        if (! is_string($conteudo) || $conteudo === '') {
            return self::$paths = ['logotipo' => null, 'subtitulo' => null];
        }

        $logotipo = preg_match('/<g id="logotipo"[^>]*>.*?<path[^>]*\bd="([^"]+)"/s', $conteudo, $matchLogo) === 1
            ? $matchLogo[1]
            : null;
        $subtitulo = preg_match('/<g id="subtitulo"[^>]*>.*?<path[^>]*\bd="([^"]+)"/s', $conteudo, $matchSub) === 1
            ? $matchSub[1]
            : null;

        $paths = ['logotipo' => $logotipo, 'subtitulo' => $subtitulo];
        try {
            Cache::store('file')->put($chave, $paths, 86400);
        } catch (Throwable) {
        }

        return self::$paths = $paths;
    }
}
