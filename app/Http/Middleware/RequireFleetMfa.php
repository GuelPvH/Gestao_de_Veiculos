<?php

namespace App\Http\Middleware;

use App\Services\Auth\FleetMfa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFleetMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->attributes->get('fleet.session');
        abort_unless($session instanceof \stdClass, 401);
        $mfa = app(FleetMfa::class);
        if ($mfa->requiredForSession($session, $request->attributes->get('fleet.link')) && ! $mfa->verified($request, $session)) {
            return $request->expectsJson() ? response()->json(['state' => 'MFA_REQUIRED'], 403) : redirect()->route('mfa.index');
        }

        return $next($request);
    }
}
