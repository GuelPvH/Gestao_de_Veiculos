<?php

namespace App\Services\Auth;

use App\Support\Value;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SecurityAudit
{
    private const EVENTS = [
        'login_sucesso', 'login_falha', 'login_credencial_vencida', 'logout',
        'administrador_inicial_criado', 'recuperacao_solicitada', 'ativacao_emitida',
        'confirmacao_email_emitida', 'alteracao_email_solicitada', 'ativacao_concluida',
        'email_confirmado', 'senha_recuperada', 'senha_alterada', 'email_falha_final',
        'email_reagendado', 'email_aceito_smtp', 'email_cancelado',
    ];

    public function record(string $event, ?int $userId, string $entity = 'usuarios', ?int $entityId = null,
        ?Request $request = null, ?string $identifier = null, ?string $correlation = null): void
    {
        abort_unless(in_array($event, self::EVENTS, true) && in_array($entity, ['usuarios', 'email_outbox'], true), 500);
        $context = [];
        if ($identifier !== null && $userId === null) {
            $context['identidade_hmac'] = $this->pseudonym($identifier);
        }
        if ($request) {
            $agent = mb_scrub((string) $request->userAgent(), 'UTF-8');
            $context['user_agent'] = mb_substr(Value::string(preg_replace('/[\x00-\x1F\x7F]/', '', $agent)), 0, 160);
        }
        DB::table('auditoria')->insert([
            'ator_usuario_id' => $userId, 'evento' => $event, 'entidade' => $entity, 'entidade_id' => $entityId,
            'correlacao' => $correlation ?? (string) Str::uuid(), 'descricao' => 'Evento de segurança registrado sem credenciais.',
            'ip' => $request ? (inet_pton((string) $request->ip()) ?: null) : null,
            'depois' => $context ? json_encode($context, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function rateLimited(string $identifier, Request $request): void
    {
        // The limiter has already bounded traffic. Do not add a DB dependency to rejection.
        Log::notice('Limite de autenticação atingido.', ['event' => 'login_bloqueado',
            'correlation' => (string) Str::uuid(), 'identity_hmac' => $this->pseudonym($identifier)]);
    }

    private function pseudonym(string $identifier): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($identifier)), Value::string(config('app.key')));
    }
}
