<?php

namespace App\Http\Middleware;

use App\Services\Auth\FleetMfa;
use App\Support\RequestState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFleetMfa
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $session = RequestState::record($request, 'fleet.session');
        abort_unless($session instanceof \stdClass, 401);
        $mfa = app(FleetMfa::class);
        if ($mfa->requiredForSession($session, RequestState::record($request, 'fleet.link')) && ! $mfa->verified($request, $session)) {
            return $request->expectsJson() ? response()->json(['state' => 'MFA_REQUIRED'], 403) : redirect()->route('mfa.index');
        }

        return $next($request);
    }
}
