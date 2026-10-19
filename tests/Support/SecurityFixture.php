<?php

namespace Tests\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Real HTTP/session/WebAuthn services on SQLite; MySQL constraints are covered separately. */
class SecurityFixture
{
    public const PASSWORD = 'SenhaSegura!123';

    public const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public static function create(): User
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'fleet.mfa.origin' => 'https://fleet.example.test', 'fleet.mfa.rp_id' => 'fleet.example.test']);
        DB::purge('sqlite');
        foreach ([
            'usuarios' => 'id INTEGER PRIMARY KEY, unidade_id INTEGER, identificador TEXT, nome TEXT, senha_hash TEXT, ativo INTEGER, deve_trocar_senha INTEGER, estado_identidade TEXT, versao_seguranca INTEGER, criado_em TEXT, atualizado_em TEXT',
            'sessoes' => 'id INTEGER PRIMARY KEY, usuario_id INTEGER, vinculo_ativo_id INTEGER, token_hash BLOB, criado_em TEXT, ultima_atividade_em TEXT, expira_em TEXT, encerrada_em TEXT, motivo_encerramento TEXT, autenticada_em TEXT, reautenticar_ate TEXT, mfa_verificada_em TEXT, mfa_vinculo_id INTEGER, mfa_versao INTEGER, mfa_http_hash BLOB',
            'auditoria' => 'id INTEGER PRIMARY KEY, ator_usuario_id INTEGER, ator_vinculo_id INTEGER, sessao_id INTEGER, evento TEXT, entidade TEXT, entidade_id INTEGER, descricao TEXT',
            'configuracao_sistema' => 'id INTEGER PRIMARY KEY, sessao_inatividade_minutos INTEGER, fuso_horario TEXT',
            'perfis' => 'id INTEGER PRIMARY KEY, nome TEXT, codigo TEXT',
            'unidades' => 'id INTEGER PRIMARY KEY, nome TEXT',
            'usuario_perfis' => 'id INTEGER PRIMARY KEY, usuario_id INTEGER, perfil_id INTEGER, unidade_id INTEGER',
            'vw_vinculos_ativos' => 'vinculo_id INTEGER, usuario_id INTEGER, perfil_id INTEGER, unidade_id INTEGER, vigente_desde TEXT, vigente_ate TEXT',
            'vw_permissoes_efetivas' => 'vinculo_id INTEGER, modulo_codigo TEXT, acao_codigo TEXT, nivel_alcance INTEGER, delegavel INTEGER',
            'credenciais_mfa' => 'id INTEGER PRIMARY KEY, usuario_id INTEGER, credential_id TEXT UNIQUE, registro TEXT, criada_em TEXT, usada_em TEXT, revogada_em TEXT',
            'desafios_mfa' => 'id INTEGER PRIMARY KEY, usuario_id INTEGER, sessao_id INTEGER, vinculo_id INTEGER, versao_seguranca INTEGER, http_hash BLOB, finalidade TEXT, opcoes TEXT, expira_em TEXT, usado_em TEXT',
            'recuperacoes_mfa' => 'id INTEGER PRIMARY KEY, usuario_id INTEGER, solicitada_por INTEGER, versao_seguranca INTEGER, justificativa TEXT, expira_em TEXT, concluida_em TEXT',
            'aprovacoes_mfa' => 'recuperacao_id INTEGER, aprovada_por INTEGER, vinculo_id INTEGER, UNIQUE(recuperacao_id,aprovada_por)',
        ] as $table => $columns) {
            DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        }
        DB::table('configuracao_sistema')->insert(['id' => 1, 'sessao_inatividade_minutos' => 30, 'fuso_horario' => 'America/Manaus']);
        DB::table('unidades')->insert(['id' => 1, 'nome' => 'Teste']);
        DB::table('perfis')->insert(['id' => 1, 'nome' => 'Administrador', 'codigo' => 'administrador']);
        $user = User::forceCreate(['id' => 1, 'unidade_id' => 1, 'identificador' => 'security-test', 'nome' => 'Security test', 'senha_hash' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]), 'ativo' => 1, 'deve_trocar_senha' => 0, 'estado_identidade' => 'ativa', 'versao_seguranca' => 1]);
        self::session(1);

        return $user;
    }

    public static function session(int $id): void
    {
        $now = CarbonImmutable::now('UTC');
        DB::table('usuario_perfis')->insert(['id' => $id, 'usuario_id' => $id, 'perfil_id' => 1, 'unidade_id' => 1]);
        DB::table('vw_vinculos_ativos')->insert(['vinculo_id' => $id, 'usuario_id' => $id, 'perfil_id' => 1, 'unidade_id' => 1, 'vigente_desde' => $now->subDay()]);
        DB::table('sessoes')->insert(['id' => $id, 'usuario_id' => $id, 'vinculo_ativo_id' => $id, 'token_hash' => hash('sha256', self::TOKEN, true), 'criado_em' => $now, 'ultima_atividade_em' => $now, 'expira_em' => $now->addMinutes(30), 'autenticada_em' => $now, 'reautenticar_ate' => $now->addMinutes(30)]);
    }
}
