<?php

use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\RequireEnabledRoute;
use App\Http\Middleware\RequireFleetMfa;
use App\Http\Middleware\RequireProfile;
use App\Http\Middleware\ValidateFleetSession;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['senha', 'senha_atual', 'nova_senha', 'nova_senha_confirmation', 'senha_temporaria', 'senha_temporaria_confirmation']);
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('FLEET_TRUSTED_PROXIES', '')))));
        if ($proxies !== []) {
            $middleware->trustProxies(at: $proxies, headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        }
        $middleware->alias([
            'fleet.session' => ValidateFleetSession::class,
            'fleet.profile' => RequireProfile::class,
            'fleet.mfa' => RequireFleetMfa::class,
            'fleet.enabled' => RequireEnabledRoute::class,
        ]);
        $middleware->appendToGroup('web', PrivateResponse::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['senha', 'senha_atual', 'nova_senha', 'nova_senha_confirmation', 'senha_temporaria', 'senha_temporaria_confirmation', 'token', 'password', 'password_confirmation']);
        $exceptions->report(function (QueryException|PDOException $error): void {
            // Never report the SQL, bindings, transport message or exception payload.
            Log::error('security.database_unavailable', [
                'correlation' => (string) Str::uuid(),
                'operation' => app()->runningInConsole() ? 'console' : (request()->route()?->getName() ?? 'http'),
                'category' => $error instanceof QueryException ? 'query' : 'connection',
                'code' => preg_match('/^[A-Z0-9]{1,10}$/', (string) $error->getCode()) ? (string) $error->getCode() : 'DATABASE_ERROR',
            ]);
        })->stop();
        $exceptions->respond(function (Response $resposta) {
            $resposta->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $resposta->headers->set('Referrer-Policy', 'no-referrer');

            return $resposta;
        });
        $exceptions->render(function (QueryException $erro, Request $requisicao) {
            return $requisicao->expectsJson()
                ? response()->json(['state' => 'SERVICE_UNAVAILABLE', 'message' => 'Serviço temporariamente indisponível.'], 503)
                : response()->view('errors.503', [], 503);
        });
        $exceptions->render(function (PDOException $erro, Request $requisicao) {
            return $requisicao->expectsJson()
                ? response()->json(['state' => 'SERVICE_UNAVAILABLE', 'message' => 'Serviço temporariamente indisponível.'], 503)
                : response()->view('errors.503', [], 503);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
