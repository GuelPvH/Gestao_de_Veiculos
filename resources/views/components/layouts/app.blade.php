<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <header class="bg-white border-bottom">
            <nav class="container py-3" aria-label="Navegação principal">
                <a class="d-inline-flex align-items-center gap-3 text-decoration-none text-dark fw-semibold" href="{{ route('home') }}">
                    <span class="brand-mark" aria-hidden="true">GV</span>
                    <span>{{ config('app.name') }}</span>
                </a>
            </nav>
        </header>

        <main>
            {{ $slot }}
        </main>
    </body>
</html>
