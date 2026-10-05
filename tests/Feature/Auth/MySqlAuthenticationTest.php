<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\ProcedureRunner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        config(['database.default' => 'mysql']);
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
        $this->assertNotSame($antes, session()->getId());
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
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/selecionar-perfil');
        $this->post('/selecionar-perfil', ['vinculo' => $alheio])->assertForbidden();
        $antes = session()->getId();
        $this->post('/selecionar-perfil', ['vinculo' => $segundo])->assertRedirect('/painel');
        $this->assertNotSame($antes, session()->getId());
        $this->assertSame((int) $segundo, (int) DB::table('sessoes')->where('id', session('fleet_session.id'))->value('vinculo_ativo_id'));
    }

    public function test_revoked_expired_and_future_links_do_not_allow_access(): void
    {
        DB::table('usuario_perfis')->where('id', $this->vinculoId)->update(['vigente_desde' => now('UTC')->addDay()]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/acesso-restrito');
        $this->post('/sair');
        DB::table('usuario_perfis')->where('id', $this->vinculoId)->update(['vigente_desde' => now('UTC')->subDays(2), 'vigente_ate' => now('UTC')->subDay()]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/acesso-restrito');
        $this->post('/sair');
        DB::table('usuario_perfis')->where('id', $this->vinculoId)->update(['vigente_ate' => null]);
        $this->login();
        $sessao = session('fleet_session.id');
        DB::table('usuario_perfis')->where('id', $this->vinculoId)->update(['ativo' => 0]);
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

    public function test_password_change_is_mandatory_and_revokes_sessions(): void
    {
        DB::table('usuarios')->where('id', $this->usuarioId)->update(['deve_trocar_senha' => 1]);
        $this->post('/entrar', ['identificador' => $this->identificador, 'senha' => $this->senha])->assertRedirect('/alterar-senha');
        $this->get('/painel')->assertRedirect('/alterar-senha');
        $nova = 'NovaSenha!'.bin2hex(random_bytes(10));
        $this->post('/alterar-senha', ['senha_atual' => $this->senha, 'nova_senha' => $nova, 'nova_senha_confirmation' => $nova])->assertRedirect('/entrar');
        $this->assertTrue(password_verify($nova, DB::table('usuarios')->where('id', $this->usuarioId)->value('senha_hash')));
        $this->assertSame(0, DB::table('sessoes')->where('usuario_id', $this->usuarioId)->whereNull('encerrada_em')->count());
    }

    public function test_recovery_is_single_use_and_rejects_expired_tokens(): void
    {
        $token = random_bytes(32);
        $hash = hash('sha256', $token, true);
        DB::table('recuperacoes_senha')->insert(['usuario_id' => $this->usuarioId, 'token_hash' => $hash, 'expira_em' => now('UTC')->addMinutes(5)]);
        $novo = password_hash('NovaSenha!'.bin2hex(random_bytes(10)), PASSWORD_BCRYPT, ['cost' => 4]);
        app(ProcedureRunner::class)->call('sp_consumir_recuperacao', [$hash, $novo]);
        try {
            app(ProcedureRunner::class)->call('sp_consumir_recuperacao', [$hash, $novo]);
            $this->fail('Token reutilizado indevidamente.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', $erro->getCode());
        }
        $expirado = hash('sha256', random_bytes(32), true);
        DB::table('recuperacoes_senha')->insert(['usuario_id' => $this->usuarioId, 'token_hash' => $expirado, 'criado_em' => now('UTC')->subHour(), 'expira_em' => now('UTC')->subMinute()]);
        try {
            app(ProcedureRunner::class)->call('sp_consumir_recuperacao',[$expirado, $novo]);
            $this->fail('Token vencido aceito indevidamente.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000',$erro->getCode());
        }
    }
}
