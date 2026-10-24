<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\FleetMfa;
use App\Support\CurrentUser;
use App\Support\RequestState;
use App\Support\Value;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function index(Request $request, FleetMfa $mfa): View|RedirectResponse
    {
        // Quem já estava nesta tela pode voltar ao painel durante a pausa local.
        if (! $mfa->enabled()) {
            return redirect()->route('dashboard');
        }
        $session = RequestState::record($request, 'fleet.session');
        $canRecover = $session instanceof \stdClass && $mfa->requiredForSession($session, RequestState::record($request, 'fleet.link')) && $mfa->verified($request, $session);

        return view('auth.mfa', ['canRecover' => $canRecover, 'registered' => DB::table('credenciais_mfa')->where('usuario_id', CurrentUser::get($request)->id)->whereNull('revogada_em')->exists(), 'recoveries' => $canRecover ? DB::table('recuperacoes_mfa')->whereNull('concluida_em')->get(['id', 'usuario_id', 'solicitada_por', 'justificativa']) : collect()]);
    }

    public function options(Request $request, FleetMfa $mfa): JsonResponse
    {
        abort_unless($mfa->enabled(), 404);
        /** @var array<string, mixed> $input */
        $input = $request->validate(['purpose' => ['required', 'in:registration,assertion']]);

        return response()->json($mfa->begin($request, Value::string($input['purpose'])))->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request, FleetMfa $mfa): JsonResponse
    {
        abort_unless($mfa->enabled(), 404);
        /** @var array<string, mixed> $input */
        $input = $request->validate(['challenge_id' => ['required', 'integer', 'min:1'], 'credential' => ['required', 'array'], 'credential.id' => ['required', 'string', 'max:1366'], 'credential.type' => ['required', 'in:public-key'], 'credential.clientExtensionResults' => ['sometimes', 'array'], 'credential.rawId' => ['required', 'string', 'max:1366'], 'credential.response' => ['required', 'array']]);
        abort_if(strlen(json_encode($input['credential'], JSON_THROW_ON_ERROR)) > 32768, 422);
        $mfa->finish($request, Value::integer($input['challenge_id']), Value::array($input['credential']));

        return response()->json(['state' => 'MFA_VERIFIED', 'redirect' => route('dashboard')])->header('Cache-Control', 'no-store, private');
    }

    public function recover(Request $request, FleetMfa $mfa): RedirectResponse
    {
        abort_unless($mfa->enabled(), 404);
        /** @var array<string, mixed> $input */
        $input = $request->validate(['usuario_id' => ['required', 'integer', 'min:1'], 'justificativa' => ['required', 'string', 'min:20', 'max:500']]);
        $id = $mfa->requestRecovery($request, Value::integer($input['usuario_id']), Value::string($input['justificativa']));

        return redirect()->route('mfa.index')->with('status', 'Pedido '.$id.' registrado. Exige duas aprovações independentes.');
    }

    public function approve(Request $request, int $recovery, FleetMfa $mfa): RedirectResponse
    {
        abort_unless($mfa->enabled(), 404);
        $mfa->approveRecovery($request, $recovery);

        return redirect()->route('mfa.index')->with('status', 'Aprovação registrada. Os fatores só serão revogados após duas aprovações independentes.');
    }
}
