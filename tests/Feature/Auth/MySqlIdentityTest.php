<?php

namespace Tests\Feature\Auth;

use App\Services\Admin\AdminWorkflow;
use App\Services\Auth\IdentityService;
use App\Services\Auth\PasswordVerifier;
use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class MySqlIdentityTest extends TestCase
{
    private int $userId;

    private string $identifier;

    protected function setUp(): void
    {
        parent::setUp();
        $configFile = getenv('FLEET_MYSQL_TEST_CONFIG');
        if (! $configFile) {
            $this->markTestSkipped('Requer MySQL descartável com patch de identidade.');
        }
        $path = realpath($configFile);
        if (! $path || str_starts_with($path, realpath(base_path()).DIRECTORY_SEPARATOR) || (fileperms($path) & 0077) !== 0) {
            throw new \RuntimeException('Configuração privada externa com chmod 600 exigida.');
        }
        $connection = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (($connection['database'] ?? '') !== 'frota_pf_contract_tests' || ! in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Alvo de teste recusado.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.unix_socket' => '',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'hashing.bcrypt.rounds' => 4]);
        foreach ($connection as $key => $value) {
            if (in_array($key, ['host', 'port', 'database', 'username', 'password'], true)) {
                config(['database.connections.mysql.'.$key => $value]);
            }
        }
        DB::purge('mysql');
        $this->assertSame('frota_pf_contract_tests', DB::selectOne('SELECT DATABASE() AS banco')->banco);
        $this->identifier = 'identity-'.bin2hex(random_bytes(8));
        $this->userId = (int) DB::table('usuarios')->insertGetId([
            'unidade_id' => 1, 'identificador' => $this->identifier, 'nome' => 'Identidade descartável',
            'email' => $this->identifier.'@local.test', 'senha_hash' => app(PasswordVerifier::class)->make('SenhaInicial!123'),
        ]);
        DB::table('usuario_perfis')->insert([
            'usuario_id' => $this->userId, 'perfil_id' => DB::table('perfis')->where('codigo', 'servidor')->value('id'),
            'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $this->userId,
        ]);
    }

    public function test_real_trigger_revokes_recovery_and_outbox_on_email_change(): void
    {
        app(IdentityService::class)->requestRecovery($this->identifier);
        $row = DB::table('email_outbox')->where('usuario_id', $this->userId)->where('finalidade', 'recuperacao')->first();
        $token = Crypt::decryptString($row->token_cifrado);
        DB::table('usuarios')->where('id', $this->userId)->update(['email' => 'new-'.$this->identifier.'@local.test']);
        $this->assertSame(2, (int) DB::table('usuarios')->where('id', $this->userId)->value('versao_seguranca'));
        $this->assertNotNull(DB::table('recuperacoes_senha')->where('id', $row->recuperacao_id)->value('invalidado_em'));
        $this->assertSame('cancelado', DB::table('email_outbox')->where('id', $row->id)->value('estado'));
        $this->expectException(ValidationException::class);
        app(IdentityService::class)->consumeRecovery($token, 'NovaSenha!456789');
    }

    public function test_case_only_email_change_revokes_exact_proof_tokens_outbox_and_session(): void
    {
        DB::table('usuarios')->where('id', $this->userId)->update(['email_verificado_em' => now('UTC')]);
        app(IdentityService::class)->requestRecovery($this->identifier);
        $row = DB::table('email_outbox')->where('usuario_id', $this->userId)->where('finalidade', 'recuperacao')->first();
        $sessionId = DB::table('sessoes')->insertGetId([
            'usuario_id' => $this->userId, 'token_hash' => random_bytes(32), 'expira_em' => now('UTC')->addMinutes(15),
        ]);
        DB::table('usuarios')->where('id', $this->userId)->update(['email' => mb_strtoupper($this->identifier.'@local.test')]);
        $user = DB::table('usuarios')->where('id', $this->userId)->first();
        $this->assertSame(2, (int) $user->versao_seguranca);
        $this->assertNull($user->email_verificado_em);
        $this->assertNotNull(DB::table('recuperacoes_senha')->where('id', $row->recuperacao_id)->value('invalidado_em'));
        $this->assertSame('cancelado', DB::table('email_outbox')->where('id', $row->id)->value('estado'));
        $this->assertSame('revogacao', DB::table('sessoes')->where('id', $sessionId)->value('motivo_encerramento'));
        $this->assertNotNull(DB::table('sessoes')->where('id', $sessionId)->value('encerrada_em'));
    }

    public function test_real_procedure_rejects_inactive_identity(): void
    {
        app(IdentityService::class)->requestRecovery($this->identifier);
        $hash = DB::table('recuperacoes_senha')->where('usuario_id', $this->userId)->value('token_hash');
        DB::table('usuarios')->where('id', $this->userId)->update(['ativo' => 0]);
        $this->expectException(\PDOException::class);
        app(ProcedureRunner::class)->call('sp_consumir_recuperacao', [$hash, app(PasswordVerifier::class)->make('NovaSenha!456789')]);
    }

    public function test_real_email_confirmation_preserves_proof_when_promoting_pending_address(): void
    {
        app(IdentityService::class)->requestEmailChange($this->userId, 'pending-'.$this->identifier.'@local.test', 'SenhaInicial!123');
        $token = Crypt::decryptString(DB::table('email_outbox')->where('usuario_id', $this->userId)->where('finalidade', 'email')->value('token_cifrado'));
        app(IdentityService::class)->consumeEmail($token);
        $user = DB::table('usuarios')->where('id', $this->userId)->first();
        $this->assertSame('pending-'.$this->identifier.'@local.test', $user->email);
        $this->assertNotNull($user->email_verificado_em);
        $this->assertNull($user->email_pendente);
        $this->assertSame(3, (int) $user->versao_seguranca);
    }

    public function test_real_creation_and_grant_roll_back_if_invitation_intent_fails(): void
    {
        $actorId = (int) DB::table('usuarios')->insertGetId([
            'unidade_id' => 1, 'identificador' => 'actor-'.$this->identifier, 'nome' => 'Administrador descartável',
            'senha_hash' => app(PasswordVerifier::class)->make('SenhaInicial!123'),
        ]);
        $actorLink = (int) DB::table('usuario_perfis')->insertGetId([
            'usuario_id' => $actorId, 'perfil_id' => DB::table('perfis')->where('codigo', 'administrador')->value('id'),
            'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $actorId,
        ]);
        app(AccessContext::class)->load(DB::table('vw_vinculos_ativos')->where('vinculo_id', $actorLink)->first());
        $roleId = (int) DB::table('perfis')->insertGetId([
            'codigo' => 'atomic-'.$this->identifier, 'nome' => 'Perfil técnico descartável', 'criado_por' => $actorId,
        ]);
        $permissionId = DB::table('permissoes')->where('modulo_codigo', 'conta')->where('acao_codigo', 'consultar')->where('alcance', 'proprios')->value('id');
        DB::table('perfil_permissoes')->insert(['perfil_id' => $roleId, 'permissao_id' => $permissionId, 'delegavel' => 0, 'concedido_por' => $actorId]);
        $this->assertTrue(app(AccessContext::class)->can('usuarios', 'criar', null, 1));
        $this->assertGreaterThanOrEqual(1, app(AccessContext::class)->delegationLevel('conta', 'consultar'));

        $newIdentifier = 'rollback-'.$this->identifier;
        $before = [];
        foreach (['usuarios', 'preferencias_usuario', 'usuario_perfis', 'confirmacoes_identidade', 'email_outbox', 'auditoria'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $realIdentity = app(IdentityService::class);
        $syntheticFailure = new \RuntimeException('Falha sintética após persistência da intenção.');
        $identity = Mockery::mock(IdentityService::class);
        $identity->shouldReceive('issueActivation')->once()->andReturnUsing(function ($user, $linkId) use ($realIdentity, $syntheticFailure): void {
            $this->assertGreaterThan(0, DB::transactionLevel());
            $this->assertSame('pendente', $user->estado_identidade);
            $this->assertSame(1, DB::table('usuario_perfis')->where('id', $linkId)->where('usuario_id', $user->id)->count());
            $realIdentity->issueActivation($user, $linkId);
            $this->assertSame(1, DB::table('confirmacoes_identidade')->where('usuario_id', $user->id)->count());
            $this->assertSame(1, DB::table('email_outbox')->where('usuario_id', $user->id)->count());
            throw $syntheticFailure;
        });
        app()->instance(IdentityService::class, $identity);
        $caught = null;
        try {
            app(AdminWorkflow::class)->storeUser([
                'unidade_id' => 1, 'identificador' => $newIdentifier, 'nome' => 'Conta que deve reverter',
                'email' => $newIdentifier.'@local.test', 'perfil_id' => $roleId,
                'vigente_desde' => now(config('fleet.timezone'))->subMinute()->format('Y-m-d\TH:i'),
                'justificativa' => 'Teste de atomicidade do cadastro',
            ]);
        } catch (\Throwable $error) {
            $caught = $error;
        } finally {
            app()->instance(IdentityService::class, $realIdentity);
        }
        $this->assertSame($syntheticFailure, $caught);
        $this->assertFalse(DB::table('usuarios')->where('identificador', $newIdentifier)->exists());
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), 'A transação precisa reverter '.$table.'.');
        }
    }

    public function test_real_initial_admin_is_pending_with_atomic_grant_and_activation_intent(): void
    {
        DB::beginTransaction();
        try {
            DB::table('configuracao_sistema')->where('id', 1)->update(['bootstrap_concluido' => 0]);
            $result = app(IdentityService::class)->createInitialAdministrator('bootstrap-'.$this->identifier,
                'Bootstrap descartável', 'bootstrap-'.$this->identifier.'@local.test', 'SenhaInicial!123');
            $user = DB::table('usuarios')->where('id', $result['usuario_id'])->first();
            $this->assertSame('pendente', $user->estado_identidade);
            $this->assertSame(1, (int) $user->deve_trocar_senha);
            $this->assertNull($user->email_verificado_em);
            $this->assertNotNull($user->senha_temporaria_expira_em);
            $this->assertSame(1, DB::table('usuario_perfis')->where('id', $result['vinculo_id'])->count());
            $outbox = DB::table('email_outbox')->where('usuario_id', $user->id)->where('finalidade', 'ativacao')->first();
            $this->assertNotNull($outbox);
            app(IdentityService::class)->consumeActivation(Crypt::decryptString($outbox->token_cifrado), 'SenhaDefinitiva!987');
            $this->assertSame('ativa', DB::table('usuarios')->where('id', $user->id)->value('estado_identidade'));
            $this->assertNotNull(DB::table('usuarios')->where('id', $user->id)->value('email_verificado_em'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_real_new_temporary_identity_from_sql_insert_is_pending_and_has_expiry(): void
    {
        $id = (int) DB::table('usuarios')->insertGetId([
            'unidade_id' => 1, 'identificador' => 'sql-pending-'.$this->identifier, 'nome' => 'Identidade SQL pendente',
            'email' => 'sql-pending-'.$this->identifier.'@local.test', 'deve_trocar_senha' => 1,
            'senha_hash' => app(PasswordVerifier::class)->make('SenhaInicial!123'),
        ]);
        $user = DB::table('usuarios')->where('id', $id)->first();
        $this->assertSame('pendente', $user->estado_identidade);
        $this->assertNotNull($user->senha_temporaria_emitida_em);
        $this->assertNotNull($user->senha_temporaria_expira_em);
        $this->assertNull($user->email_verificado_em);
    }
}
