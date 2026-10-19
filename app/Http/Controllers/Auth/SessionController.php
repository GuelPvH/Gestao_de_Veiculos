<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FleetMfa;
use App\Services\Auth\FleetSession;
use App\Services\Auth\PasswordVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class SessionController extends Controller
{
    public function status(Request $request, FleetSession $sessions): JsonResponse
    {
        $row = $request->attributes->get('fleet.session');
        abort_unless($row instanceof \stdClass, 401);

        return $this->state($sessions, $row);
    }

    public function reauthenticate(Request $request, FleetSession $sessions, PasswordVerifier $passwords): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $key = 'fleet-reauth:'.$user->id;
        $ipKey = $key.':'.hash('sha256', (string) $request->ip());
        foreach ([$key => 10, $ipKey => 5] as $limitKey => $limit) {
            if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
                return response()->json(['state' => 'RATE_LIMITED'], 429)->header('Retry-After', (string) RateLimiter::availableIn($limitKey));
            }
            RateLimiter::hit($limitKey, 60);
        }
        $input = $request->validate(['senha' => ['required', 'string', 'max:1024']]);
        $snapshot = clone $user;
        if (! $passwords->verify($input['senha'], $snapshot->senha_hash)) {
            return response()->json(['state' => 'INVALID_CREDENTIAL', 'message' => 'Não foi possível confirmar sua senha.'], 422);
        }
        $token = bin2hex(random_bytes(32));
        $row = DB::transaction(function () use ($request, $sessions, $snapshot, $token): ?\stdClass {
            $current = DB::table('usuarios')->where('id', $snapshot->id)->lockForUpdate()->first();
            $row = $sessions->locked($request, (int) $snapshot->id);
            if (! $sessions->eligible($row, $current)) {
                return null;
            }
            // Password verification may run before a concurrent password change wins the lock.
            if (! hash_equals($snapshot->senha_hash, $current->senha_hash) || $current->deve_trocar_senha) {
                return null;
            }
            $now = CarbonImmutable::now('UTC');
            $minutes = (int) DB::table('configuracao_sistema')->where('id', 1)->value('sessao_inatividade_minutos');
            abort_unless($minutes > 0, 503);
            $changes = ['token_hash' => hash('sha256', $token, true), 'autenticada_em' => $now->format('Y-m-d H:i:s.u'), 'reautenticar_ate' => $now->addMinutes(30)->format('Y-m-d H:i:s.u'), 'ultima_atividade_em' => $now->format('Y-m-d H:i:s.u'), 'expira_em' => $now->addMinutes($minutes)->format('Y-m-d H:i:s.u'), 'mfa_verificada_em' => null, 'mfa_vinculo_id' => null, 'mfa_versao' => null, 'mfa_http_hash' => null];
            DB::table('sessoes')->where('id', $row->id)->update($changes);
            $sessions->audit($row, 'reautenticacao', 'senha_confirmada');

            return (object) array_replace((array) $row, $changes);
        }, 3);
        abort_unless($row !== null, 401, 'SESSION_ENDED');
        $request->session()->put('fleet_session.token', $token);
        $request->session()->migrate(true);
        RateLimiter::clear($ipKey);

        return $this->state($sessions, $row)->setData($this->payload($sessions, $row) + ['mfa_required' => app(FleetMfa::class)->requiredForSession($row, $request->attributes->get('fleet.link'))]);
    }

    private function state(FleetSession $sessions, \stdClass $row): JsonResponse
    {
        return response()->json($this->payload($sessions, $row))->header('Cache-Control', 'no-store, private');
    }

    private function payload(FleetSession $sessions, \stdClass $row): array
    {
        return ['state' => 'ACTIVE', 'server_time' => CarbonImmutable::now('UTC')->toIso8601String(), 'expires_at' => $sessions->deadline($row)->toIso8601String()];
    }
}
