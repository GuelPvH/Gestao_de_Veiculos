<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\ProcedureRunner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MySqlAuthenticationTest extends TestCase
{
    private int $usuarioId;

    private int $vinculoId;

    private string $identificador;

    private string $senha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $arquivo = getenv('FLEET_MYSQL_TEST_CONFIG');
        if (! $arquivo) {
            $this->markTestSkipped('Requer MySQL descartável provisionado, com configuração privada.');
        }
        $caminho = realpath($arquivo);
        $raiz = realpath(base_path());
        if (! $caminho || str_starts_with($caminho, $raiz.DIRECTORY_SEPARATOR) || (fileperms($caminho) & 0077) !== 0) {
            throw new \RuntimeException('Configuração de teste precisa ficar fora do Git e com chmod 600.');
        }
        $conexao = json_decode(file_get_contents($caminho), true, 512, JSON_THROW_ON_ERROR);
        if (($conexao['database'] ?? '') !== 'frota_pf_contract_tests' || ! in_array($conexao['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
            throw new \RuntimeException('Alvo de teste recusado.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.unix_socket' => '']);
        foreach ($conexao as $chave => $valor) {
            if (in_array($chave, ['host', 'port', 'database', 'username', 'password'], true)) {
                config(['database.connections.mysql.'.$chave => $valor]);
            }
        }
        DB::purge('mysql');
        Auth::forgetGuards();
        $this->assertSame('frota_pf_contract_tests', DB::selectOne('SELECT DATABASE() AS banco')->banco);
        $this->senha = 'AcessoPrivado!'.bin2hex(random_bytes(10));
        $this->identificador = 'qa-'.bin2hex(random_bytes(8));
        $this->usuarioId = DB::table('usuarios')->insertGetId(['unidade_id' => 1, 'identificador' => $this->identificador, 'nome' => 'Conta de teste isolado', 'senha_hash' => password_hash($this->senha, PASSWORD_BCRYPT, ['cost' => 4])]);
        $perfil = (int) DB::table('perfis')->where('codigo', 'servidor')->value('id');
        $this->vinculoId = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $this->usuarioId, 'perfil_id' => $perfil, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $this->usuarioId]);
    }

    protected function tearDown(): void
    {
        if (isset($this->usuarioId)) {
            Auth::logout();
        } parent::tearDown();
    }

    private function login(): void
    {
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/painel');
    }

    public function test_real_login_hashes_token_and_logout_closes_session(): void
    {
        $antes = session()->getId();
        $this->login();
        $this->assertFalse(hash_equals($antes, session()->getId()));
        $token = session('fleet_session.token');
        $id = session('fleet_session.id');
        $row = DB::table('sessoes')->where('id', $id)->first();
        $this->assertSame(32, strlen($row->token_hash));
        $this->assertSame(hash('sha256', $token, true), $row->token_hash);
        $this->assertSame($this->vinculoId, (int) $row->vinculo_ativo_id);
        $this->post('/sair')->assertRedirect('/entrar');
        $this->assertNotNull(DB::table('sessoes')->where('id', $id)->value('encerrada_em'));
        $this->get('/painel')->assertRedirect('/entrar');
    }

    public function test_wrong_password_inactive_and_no_link_are_rejected(): void
    {
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => 'Errada'])->assertSessionHasErrors('identificador');
        DB::table('usuarios')->where('id', $this->usuarioId)->update(['ativo' => 0]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertSessionHasErrors('identificador');
        DB::table('usuarios')->where('id', $this->usuarioId)->update(['ativo' => 1]);
        DB::table('usuario_perfis')->where('id', $this->vinculoId)->update(['ativo' => 0]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/acesso-restrito');
        $this->assertSame(0, DB::table('sessoes')->where('usuario_id', $this->usuarioId)->count());
    }

    public function test_multiple_links_need_selection_and_foreign_links_are_denied(): void
    {
        $perfil = (int) DB::table('perfis')->where('codigo', 'gestor')->value('id');
        $segundo = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $this->usuarioId, 'perfil_id' => $perfil, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $this->usuarioId]);
        $outro = DB::table('usuarios')->insertGetId(['unidade_id' => 1, 'identificador' => 'outro-'.bin2hex(random_bytes(8)), 'nome' => 'Outra conta isolada', 'senha_hash' => password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT, ['cost' => 4])]);
        $alheio = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $outro, 'perfil_id' => $perfil, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $this->usuarioId]);
        $cookieName = config('session.cookie');
        $login = $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/selecionar-perfil');
        $cookieBeforeSelection = $login->getCookie($cookieName)?->getValue();
        $this->assertNotNull($cookieBeforeSelection);

        $foreignSelection = $this->withCookie($cookieName, $cookieBeforeSelection)->post('/selecionar-perfil', ['vinculo' => $alheio])->assertForbidden();
        $cookieBeforeSelection = $foreignSelection->getCookie($cookieName)?->getValue() ?? $cookieBeforeSelection;

        $selection = $this->withCookie($cookieName, $cookieBeforeSelection)->post('/selecionar-perfil', ['vinculo' => $segundo])->assertRedirect('/painel');
        $cookieAfterSelection = $selection->getCookie($cookieName)?->getValue();
        $this->assertNotNull($cookieAfterSelection);
        $this->assertFalse(hash_equals($cookieBeforeSelection, $cookieAfterSelection));
        $this->assertSame((int) $segundo, (int) DB::table('sessoes')->where('id', session('fleet_session.id'))->value('vinculo_ativo_id'));

        session()->flush();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $cookieBeforeSelection)->get('/selecionar-perfil')->assertRedirect('/entrar');
        session()->flush();
        Auth::forgetGuards();
        $this->withCookie($cookieName, $cookieAfterSelection)->get('/selecionar-perfil')->assertOk();
    }

    public function test_revoked_expired_and_future_links_do_not_allow_access(): void
    {
        DB::table('usuario_perfis')->where('id', $this->vinculoId)->update(['ativo' => 0]);
        $perfil = (int) DB::table('perfis')->where('codigo', 'servidor')->value('id');
        $futuro = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $this->usuarioId, 'perfil_id' => $perfil, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->addDay(), 'concedido_por' => $this->usuarioId]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/acesso-restrito');
        $this->post('/sair');
        DB::table('usuario_perfis')->where('id', $futuro)->update(['ativo' => 0]);
        DB::table('usuario_perfis')->insert(['usuario_id' => $this->usuarioId, 'perfil_id' => $perfil, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subDays(2), 'vigente_ate' => now('UTC')->subDay(), 'concedido_por' => $this->usuarioId]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/acesso-restrito');
        $this->post('/sair');
        $vigente = DB::table('usuario_perfis')->insertGetId(['usuario_id' => $this->usuarioId, 'perfil_id' => $perfil, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->subMinute(), 'concedido_por' => $this->usuarioId]);
        $this->login();
        $sessao = session('fleet_session.id');
        DB::table('usuario_perfis')->where('id', $vigente)->update(['ativo' => 0]);
        $this->get('/painel')->assertRedirect('/entrar');
        $this->assertNotNull(DB::table('sessoes')->where('id', $sessao)->value('encerrada_em'));
    }

    public function test_inactivity_and_token_mismatch_are_rejected(): void
    {
        $this->login();
        $id = session('fleet_session.id');
        DB::table('sessoes')->where('id', $id)->update(['criado_em' => now('UTC')->subHours(2), 'ultima_atividade_em' => now('UTC')->subHour(), 'expira_em' => now('UTC')->subMinute()]);
        $this->get('/painel')->assertRedirect('/entrar');
        $this->assertSame('inatividade', DB::table('sessoes')->where('id', $id)->value('motivo_encerramento'));
        $this->login();
        $this->withSession(['fleet_session' => ['id' => session('fleet_session.id'), 'token' => str_repeat('f', 64)]])->get('/painel')->assertRedirect('/entrar');
    }

    public function test_absolute_session_deadline_expires_even_when_recently_active(): void
    {
        $this->login();
        $sessao = session('fleet_session.id');
        $limite = (int) config('fleet.session_max_lifetime_minutes');
        DB::table('sessoes')->where('id', $sessao)->update([
            'criado_em' => now('UTC')->subMinutes($limite + 1),
            'ultima_atividade_em' => now('UTC'),
            'expira_em' => now('UTC')->addMinutes(20),
        ]);

        $this->get('/painel')->assertRedirect('/entrar');
        $this->assertSame('prazo', DB::table('sessoes')->where('id', $sessao)->value('motivo_encerramento'));
    }

    public function test_database_failure_during_activity_validation_fails_closed(): void
    {
        $this->login();
        $sessao = session('fleet_session.id');
        $procedimentos = \Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()->withArgs(fn (string $nome): bool => $nome === 'sp_registrar_atividade')
            ->andThrow(new \PDOException('Falha simulada de banco.', 2002));
        $this->app->instance(ProcedureRunner::class, $procedimentos);

        $this->get('/painel')->assertStatus(503);
        $this->assertNull(DB::table('sessoes')->where('id', $sessao)->value('encerrada_em'));
    }

    public function test_password_change_is_mandatory_and_revokes_sessions(): void
    {
        DB::table('usuarios')->where('id', $this->usuarioId)->update(['deve_trocar_senha' => 1]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/alterar-senha');
        $this->get('/painel')->assertRedirect('/alterar-senha');
        $segundaSessao = DB::table('sessoes')->insertGetId([
            'usuario_id' => $this->usuarioId,
            'token_hash' => random_bytes(32),
            'expira_em' => now('UTC')->addHour(),
        ]);
        $recuperacaoPendente = DB::table('recuperacoes_senha')->insertGetId([
            'usuario_id' => $this->usuarioId,
            'token_hash' => random_bytes(32),
            'expira_em' => now('UTC')->addMinutes(30),
        ]);
        $nova = 'NovaSenha!'.bin2hex(random_bytes(10));
        $this->post('/alterar-senha', ['senha_atual' => $this->senha, 'nova_senha' => $nova, 'nova_senha_confirmation' => $nova])->assertRedirect('/entrar');
        $this->assertTrue(password_verify($nova, DB::table('usuarios')->where('id', $this->usuarioId)->value('senha_hash')));
        $this->assertSame(0, DB::table('sessoes')->where('usuario_id', $this->usuarioId)->whereNull('encerrada_em')->count());
        $this->assertSame('troca_senha', DB::table('sessoes')->where('id', $segundaSessao)->value('motivo_encerramento'));
        $this->assertNotNull(DB::table('recuperacoes_senha')->where('id', $recuperacaoPendente)->value('invalidado_em'));
    }

    public function test_wrong_current_password_attempts_are_throttled_per_user_and_ip(): void
    {
        $this->login();
        RateLimiter::clear('fleet-password-user:'.hash('sha256', (string) $this->usuarioId));
        RateLimiter::clear('fleet-password-user-ip:'.hash('sha256', $this->usuarioId.'|127.0.0.1'));
        $nova = 'SenhaNova!'.bin2hex(random_bytes(10));
        for ($i = 0; $i < 5; $i++) {
            $this->from('/alterar-senha')->post('/alterar-senha', [
                'senha_atual' => 'Senha atual incorreta',
                'nova_senha' => $nova,
                'nova_senha_confirmation' => $nova,
            ])->assertSessionHasErrors('senha_atual');
        }
        $this->post('/alterar-senha', [
            'senha_atual' => 'Senha atual incorreta',
            'nova_senha' => $nova,
            'nova_senha_confirmation' => $nova,
        ])->assertStatus(429);

        $chaveUsuario = 'fleet-password-user:'.hash('sha256', (string) $this->usuarioId);
        RateLimiter::clear($chaveUsuario);
        $ips = ['192.0.2.11', '192.0.2.12', '192.0.2.13'];
        foreach ($ips as $ip) {
            RateLimiter::clear('fleet-password-user-ip:'.hash('sha256', $this->usuarioId.'|'.$ip));
            for ($i = 0; $i < 5; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/alterar-senha')->post('/alterar-senha', [
                    'senha_atual' => 'Senha atual incorreta',
                    'nova_senha' => $nova,
                    'nova_senha_confirmation' => $nova,
                ])->assertSessionHasErrors('senha_atual');
            }
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.15'])->post('/alterar-senha', [
            'senha_atual' => 'Senha atual incorreta',
            'nova_senha' => $nova,
            'nova_senha_confirmation' => $nova,
        ])->assertStatus(429);
    }

    public function test_recovery_is_single_use_and_rejects_expired_tokens(): void
    {
        $this->login();
        $sessao = session('fleet_session.id');
        $token = random_bytes(32);
        $hash = hash('sha256', $token, true);
        DB::table('recuperacoes_senha')->insert(['usuario_id' => $this->usuarioId, 'token_hash' => $hash, 'expira_em' => now('UTC')->addMinutes(5)]);
        $this->assertSame($hash, DB::table('recuperacoes_senha')->where('usuario_id', $this->usuarioId)->value('token_hash'));
        $novo = password_hash('NovaSenha!'.bin2hex(random_bytes(10)), PASSWORD_BCRYPT, ['cost' => 4]);
        app(ProcedureRunner::class)->call('sp_consumir_recuperacao', [$hash, $novo]);
        $this->assertSame('troca_senha', DB::table('sessoes')->where('id', $sessao)->value('motivo_encerramento'));
        $this->assertNotNull(DB::table('sessoes')->where('id', $sessao)->value('encerrada_em'));
        $this->get('/painel')->assertRedirect('/entrar');
        try {
            app(ProcedureRunner::class)->call('sp_consumir_recuperacao', [$hash, $novo]);
            $this->fail('Token reutilizado indevidamente.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', $erro->getCode());
        }
        $expirado = hash('sha256', random_bytes(32), true);
        DB::table('recuperacoes_senha')->insert(['usuario_id' => $this->usuarioId, 'token_hash' => $expirado, 'criado_em' => now('UTC')->subHour(), 'expira_em' => now('UTC')->subMinute()]);
        try {
            app(ProcedureRunner::class)->call('sp_consumir_recuperacao', [$expirado, $novo]);
            $this->fail('Token vencido aceito indevidamente.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', $erro->getCode());
        }
    }

    public function test_concurrent_recovery_consumption_is_serialized_by_user_lock(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('Requer processos locais para testar concorrência MySQL.');
        }

        $configPath = realpath((string) getenv('FLEET_MYSQL_TEST_CONFIG'));
        $this->assertNotFalse($configPath);
        $workerPath = base_path('tests/Support/consume-recovery-worker.php');
        $this->assertFileExists($workerPath);

        $tokenHash = hash('sha256', random_bytes(32), true);
        DB::table('recuperacoes_senha')->insert([
            'usuario_id' => $this->usuarioId,
            'token_hash' => $tokenHash,
            'expira_em' => now('UTC')->addMinutes(5),
        ]);

        $directory = sys_get_temp_dir().'/frota-recovery-race-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $processes = [];
        $connection = DB::connection()->getPdo();
        $connection->beginTransaction();

        try {
            $lock = $connection->prepare('SELECT id FROM usuarios WHERE id = ? FOR UPDATE');
            $lock->execute([$this->usuarioId]);
            $this->assertSame($this->usuarioId, (int) $lock->fetchColumn());

            for ($index = 0; $index < 2; $index++) {
                $entrada = $directory.'/entrada-'.$index.'.json';
                $pronto = $directory.'/pronto-'.$index;
                $resultado = $directory.'/resultado-'.$index;
                file_put_contents($entrada, json_encode([
                    'token_hash' => bin2hex($tokenHash),
                    'novo_hash' => password_hash('Concorrente!'.bin2hex(random_bytes(12)), PASSWORD_BCRYPT, ['cost' => 4]),
                ], JSON_THROW_ON_ERROR));
                chmod($entrada, 0600);

                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $workerPath, $configPath, $entrada, $pronto, $resultado],
                    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                    $pipes,
                    base_path(),
                );
                if (! is_resource($process)) {
                    throw new \RuntimeException('Não foi possível iniciar o worker de concorrência.');
                }

                $processes[] = ['process' => $process, 'ready' => $pronto, 'result' => $resultado];
            }

            $prazo = microtime(true) + 5;
            while (microtime(true) < $prazo) {
                if (count(array_filter($processes, fn (array $item): bool => is_file($item['ready']))) === 2) {
                    break;
                }
                usleep(10000);
            }
            $this->assertCount(2, array_filter($processes, fn (array $item): bool => is_file($item['ready'])));

            // Os dois workers já abriram conexões próprias e iniciam a procedure sob o mesmo lock.
            usleep(100000);
            $connection->commit();

            $resultados = [];
            foreach ($processes as $item) {
                $this->assertSame(0, proc_close($item['process']));
                $resultados[] = file_get_contents($item['result']);
            }
            sort($resultados);
            $this->assertSame(['rejected', 'success'], $resultados);
            $this->assertNotNull(DB::table('recuperacoes_senha')->where('token_hash', $tokenHash)->value('usado_em'));
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            foreach ($processes as $item) {
                if (is_resource($item['process'])) {
                    proc_terminate($item['process']);
                    proc_close($item['process']);
                }
            }
            foreach (glob($directory.'/*') ?: [] as $arquivo) {
                unlink($arquivo);
            }
            rmdir($directory);
        }
    }
}
