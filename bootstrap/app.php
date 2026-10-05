<?php

use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\RequireEnabledRoute;
use App\Http\Middleware\RequireProfile;
use App\Http\Middleware\ValidateFleetSession;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['senha', 'senha_atual', 'nova_senha', 'nova_senha_confirmation']);
        $middleware->alias([
            'fleet.session' => ValidateFleetSession::class,
            'fleet.profile' => RequireProfile::class,
            'fleet.enabled' => RequireEnabledRoute::class,
        ]);
        $middleware->appendToGroup('web', PrivateResponse::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['senha', 'senha_atual', 'nova_senha', 'nova_senha_confirmation', 'token', 'password', 'password_confirmation']);
        $exceptions->dontReport([QueryException::class, PDOException::class]);
        $exceptions->respond(function (Response $resposta) {
            $resposta->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $resposta->headers->set('Referrer-Policy', 'no-referrer');

            return $resposta;
        });
        $exceptions->render(function (QueryException $erro, Request $requisicao) {
            return response()->view('errors.503', [], 503);
        });
        $exceptions->render(function (PDOException $erro, Request $requisicao) {
            return response()->view('errors.503', [], 503);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
