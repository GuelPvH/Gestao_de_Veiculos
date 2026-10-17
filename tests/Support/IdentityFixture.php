<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

class IdentityFixture
{
    public static function create(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
CREATE TABLE usuarios (id INTEGER PRIMARY KEY, identificador TEXT UNIQUE, nome TEXT, email TEXT UNIQUE, email_pendente TEXT,
 email_verificado_em TEXT, ativo INTEGER DEFAULT 1, senha_hash TEXT, unidade_id INTEGER DEFAULT 1,
 deve_trocar_senha INTEGER DEFAULT 0, estado_identidade TEXT DEFAULT 'legado', versao_seguranca INTEGER DEFAULT 1,
 senha_alterada_em TEXT, senha_temporaria_emitida_em TEXT, senha_temporaria_expira_em TEXT,
 senha_temporaria_usada_em TEXT, senha_temporaria_revogada_em TEXT, versao INTEGER DEFAULT 1);
CREATE TABLE recuperacoes_senha (id INTEGER PRIMARY KEY, usuario_id INTEGER, token_hash BLOB UNIQUE,
 email_destino TEXT, versao_seguranca INTEGER, criado_em TEXT, expira_em TEXT, usado_em TEXT, invalidado_em TEXT);
CREATE TABLE confirmacoes_identidade (id INTEGER PRIMARY KEY, usuario_id INTEGER, vinculo_id INTEGER,
 finalidade TEXT, email_destino TEXT, versao_seguranca INTEGER, token_hash BLOB UNIQUE, criado_em TEXT,
 expira_em TEXT, usado_em TEXT, invalidado_em TEXT);
CREATE TABLE email_outbox (id INTEGER PRIMARY KEY, evento_chave TEXT UNIQUE, usuario_id INTEGER, finalidade TEXT,
 versao_seguranca INTEGER, destinatario_cifrado TEXT, token_cifrado TEXT, recuperacao_id INTEGER, confirmacao_id INTEGER,
 aviso_codigo TEXT, estado TEXT, tentativas INTEGER, proxima_tentativa_em TEXT, expira_em TEXT, lease_id TEXT,
 lease_ate TEXT, aceito_smtp_em TEXT, provedor_id TEXT, codigo_erro TEXT, criado_em TEXT);
CREATE TABLE sessoes (id INTEGER PRIMARY KEY, usuario_id INTEGER, encerrada_em TEXT, motivo_encerramento TEXT);
CREATE TABLE auditoria (id INTEGER PRIMARY KEY, ator_usuario_id INTEGER, evento TEXT, entidade TEXT,
 entidade_id INTEGER, correlacao TEXT, descricao TEXT, ip BLOB, depois TEXT, criado_em TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE configuracao_sistema (id INTEGER PRIMARY KEY, bootstrap_concluido INTEGER DEFAULT 0);
CREATE TABLE perfis (id INTEGER PRIMARY KEY, codigo TEXT, ativo INTEGER DEFAULT 1);
CREATE TABLE preferencias_usuario (usuario_id INTEGER PRIMARY KEY);
CREATE TABLE usuario_perfis (id INTEGER PRIMARY KEY, usuario_id INTEGER, perfil_id INTEGER, unidade_id INTEGER, vigente_desde TEXT, concedido_por INTEGER);
CREATE TABLE vw_vinculos_ativos (vinculo_id INTEGER PRIMARY KEY, usuario_id INTEGER);
SQL);
        DB::table('usuarios')->insert([
            'id' => 1, 'identificador' => 'qa-recuperacao', 'nome' => 'Conta de teste',
            'email' => 'qa-recuperacao@local.test', 'senha_hash' => password_hash('SenhaInicial!123', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        DB::table('usuarios')->insert([
            'id' => 2, 'identificador' => 'qa-inativo', 'nome' => 'Conta inativa', 'email' => 'qa-inativo@local.test',
            'ativo' => 0, 'senha_hash' => password_hash('SenhaInicial!123', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        DB::table('usuarios')->insert([
            'id' => 3, 'identificador' => 'qa-sem-email', 'nome' => 'Conta sem e-mail',
            'senha_hash' => password_hash('SenhaInicial!123', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        DB::table('vw_vinculos_ativos')->insert(['vinculo_id' => 10, 'usuario_id' => 1]);
        DB::table('configuracao_sistema')->insert(['id' => 1]);
        DB::table('perfis')->insert(['id' => 4, 'codigo' => 'administrador']);
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public static function mailSettings(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.env' => 'testing',
            'app.url' => 'https://frota.example.test', 'fleet.recovery_mail_enabled' => true,
            'fleet.recovery_queue_enabled' => true, 'fleet.recovery_queue_connection' => 'database',
            'queue.connections.database.driver' => 'database', 'identity.activation_mail_enabled' => true,
            'identity.verification_mail_enabled' => true, 'identity.security_mail_enabled' => true,
            'mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => '127.0.0.1', 'mail.from.address' => 'no-reply@frota.example.test',
        ]);
    }
}
