<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class MySqlWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $arquivo = getenv('FLEET_MYSQL_TEST_CONFIG');
        if (! $arquivo) {
            $this->markTestSkipped('Requer MySQL descartável provisionado pelo contrato de integração.');
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
        $this->assertStringContainsString('frota_runtime', DB::selectOne('SELECT CURRENT_USER() AS usuario')->usuario);
    }

    public function test_request_trip_and_both_monitoring_sources_generate_private_csv_with_revocation(): void
    {
        Storage::fake('local');
        [$servidor, $senhaServidor] = $this->user('servidor');
        [$gestor, $senhaGestor] = $this->user('gestor');
        $placa = strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));
        $veiculo = DB::table('veiculos')->insertGetId([
            'unidade_id' => 1, 'categoria_id' => 1, 'nome' => 'Veículo QA isolado',
            'placa' => $placa, 'capacidade' => 4, 'criado_por' => $gestor,
        ]);
        DB::table('motoristas')->insert([
            'usuario_id' => $servidor, 'numero_cnh' => bin2hex(random_bytes(7)),
            'categoria_cnh' => 'B', 'categorias_autorizadas' => 'B',
            'validade_cnh' => CarbonImmutable::now('UTC')->addYear()->toDateString(),
        ]);

        $this->login($servidor, $senhaServidor);
        $local = CarbonImmutable::now(config('fleet.timezone'));
        $this->post(route('requests.store'), [
            'finalidade' => 'Inspeção QA isolada', 'origem' => 'Sede', 'destino' => 'Unidade de teste',
            'saida_prevista' => $local->subHours(2)->format('Y-m-d\TH:i'),
            'retorno_previsto' => $local->addHours(2)->format('Y-m-d\TH:i'),
            'quantidade_passageiros' => 1, 'necessita_motorista' => '1',
            'veiculo_pretendido_id' => $veiculo,
        ])->assertSessionHas('status');
        $solicitacao = (int) DB::table('solicitacoes')->where('solicitante_id', $servidor)->orderByDesc('id')->value('id');
        $this->assertGreaterThan(0, $solicitacao);
        $this->assertSame('rascunho', DB::table('solicitacoes')->where('id', $solicitacao)->value('situacao'));
        $this->post(route('requests.perform', ['registro' => $solicitacao, 'acao' => 'send']), ['versao' => 2])
            ->assertSessionHas('status');
        $this->assertSame('aguardando_analise', DB::table('solicitacoes')->where('id', $solicitacao)->value('situacao'));
        $this->post(route('requests.perform', ['registro' => $solicitacao, 'acao' => 'send']), ['versao' => 2])
            ->assertForbidden();

        $this->post('/sair');
        $this->login($gestor, $senhaGestor);
        $this->post(route('requests.perform', ['registro' => $solicitacao, 'acao' => 'approve']), [
            'versao' => 3, 'veiculo_confirmado_id' => $veiculo,
            'motorista_confirmado_id' => $servidor, 'justificativa' => 'Agenda e habilitação conferidas.',
        ])->assertSessionHas('status');
        $viagem = (int) DB::table('viagens as v')->join('solicitacao_revisoes as r', 'r.id', '=', 'v.revisao_id')
            ->where('r.solicitacao_id', $solicitacao)->value('v.id');
        $this->assertGreaterThan(0, $viagem);
        $this->assertSame('programada', DB::table('viagens')->where('id', $viagem)->value('situacao'));
        $this->assertSame(1, DB::table('reservas')->where('veiculo_id', $veiculo)->where('situacao', 'ativa')->count());

        $this->post('/sair');
        $this->login($servidor, $senhaServidor);
        $this->post(route('trips.perform', ['registro' => $viagem, 'acao' => 'departure']), [
            'versao' => 1, 'data_registro' => $local->subMinutes(30)->format('Y-m-d\TH:i'),
            'quilometragem' => '25000.0', 'item_1' => 'ok', 'item_2' => 'ok', 'item_3' => 'ok',
        ])->assertSessionHas('status');
        $this->assertSame('em_andamento', DB::table('viagens')->where('id', $viagem)->value('situacao'));
        $this->post(route('trips.perform', ['registro' => $viagem, 'acao' => 'occurrence']), [
            'versao' => 2, 'tipo' => 'atraso', 'ocorrido_em' => $local->subMinutes(15)->format('Y-m-d\TH:i'),
            'descricao' => 'Ocorrência QA após saída.',
        ])->assertSessionHas('status');
        $this->assertSame(1, DB::table('viagem_ocorrencias')->where('viagem_id', $viagem)->count());
        $this->post(route('trips.perform', ['registro' => $viagem, 'acao' => 'return']), [
            'versao' => 3, 'data_registro' => $local->subMinutes(5)->format('Y-m-d\TH:i'),
            'quilometragem' => '25012.0', 'item_1' => 'ok', 'item_2' => 'ok', 'item_3' => 'ok',
        ])->assertSessionHas('status');
        $this->assertSame('concluida', DB::table('viagens')->where('id', $viagem)->value('situacao'));
        $this->assertSame('liberada', DB::table('reservas')->where('veiculo_id', $veiculo)->value('situacao'));

        $rastreador = DB::table('rastreadores')->insertGetId([
            'identificador_externo' => bin2hex(random_bytes(8)), 'provedor' => 'QA',
        ]);
        $instalacao = DB::table('veiculo_rastreadores')->insertGetId([
            'veiculo_id' => $veiculo, 'rastreador_id' => $rastreador,
            'instalado_em' => CarbonImmutable::now('UTC')->subHour()->format('Y-m-d H:i:s.u'),
            'instalado_por' => $gestor,
        ]);
        for ($indice = 1; $indice <= 11; $indice++) {
            DB::table('posicoes_rastreamento')->insert([
                'instalacao_id' => $instalacao, 'veiculo_id' => $veiculo, 'viagem_id' => $viagem,
                'identificador_evento' => 'qa-'.$indice,
                'capturado_em' => CarbonImmutable::now('UTC')->subMinutes(6 + $indice)->format('Y-m-d H:i:s.u'),
                'latitude' => -8.76, 'longitude' => -63.90,
            ]);
        }
        DB::table('posicoes_manuais')->insert([
            'viagem_id' => $viagem, 'registrado_por' => $servidor,
            'ocorrido_em' => CarbonImmutable::now('UTC')->subMinutes(10)->format('Y-m-d H:i:s.u'),
            'latitude' => -8.75, 'longitude' => -63.91, 'descricao' => 'Posição declarada no teste.',
        ]);

        $this->post('/sair');
        $this->login($gestor, $senhaGestor);
        $filtros = ['modulo' => 'monitoring', 'q' => $placa,
            'de' => $local->subDay()->toDateString(), 'ate' => $local->toDateString(),
            'campos' => ['placa', 'capturado_em', 'fonte']];
        $this->get(route('reports.index', $filtros))->assertOk()->assertSee('manual')->assertSee('rastreador');
        $this->post(route('reports.export'), $filtros)->assertSessionHasNoErrors();
        $exportacao = DB::table('exportacoes')->where('modulo_codigo', 'rastreamento')->orderByDesc('id')->first();
        $this->assertNotNull($exportacao);
        $this->assertSame('concluida', $exportacao->situacao);
        $this->assertSame(12, (int) $exportacao->total_registros);
        $this->assertSame(11, DB::table('exportacao_registros')->where('exportacao_id', $exportacao->id)->whereNotNull('posicao_rastreamento_id')->count());
        $this->assertSame(1, DB::table('exportacao_registros')->where('exportacao_id', $exportacao->id)->whereNotNull('posicao_manual_id')->count());
        $arquivo = DB::table('arquivos')->where('id', $exportacao->arquivo_id)->first();
        Storage::disk('local')->assertExists($arquivo->chave_armazenamento);
        $conteudo = Storage::disk('local')->get($arquivo->chave_armazenamento);
        $this->assertSame(13, count(array_filter(explode("\n", trim($conteudo)))));
        $this->assertStringContainsString('manual', $conteudo);
        $this->get(route('reports.download', $exportacao->id))->assertOk();

        $raizOriginal = config('filesystems.disks.local.root');
        config(['filesystems.disks.local.root' => '/dev/null']);
        Storage::forgetDisk('local');
        $this->withoutExceptionHandling();
        try {
            $this->post(route('reports.export'), $filtros);
            $this->fail('A gravação no caminho inválido não foi recusada.');
        } catch (\Throwable $erro) {
            $this->assertNotSame(AssertionFailedError::class, $erro::class);
        } finally {
            config(['filesystems.disks.local.root' => $raizOriginal]);
            Storage::forgetDisk('local');
            $this->withExceptionHandling();
        }
        $exportacaoFalha = DB::table('exportacoes')->where('modulo_codigo', 'rastreamento')->orderByDesc('id')->first();
        $this->assertNotSame($exportacao->id, $exportacaoFalha->id);
        $this->assertSame('falhou', $exportacaoFalha->situacao);
        $this->assertNull($exportacaoFalha->arquivo_id);
        $this->assertSame(0, DB::table('arquivos')->where('nome_original', 'frota-pf-relatorio-'.$exportacaoFalha->id.'.csv')->count());

        DB::table('perfil_permissoes')->where('perfil_id', 2)->where('permissao_id', 72)->delete();
        $this->get(route('reports.download', $exportacao->id))->assertForbidden();
    }

    public function test_admin_delegates_with_real_procedures_and_disables_only_a_post_route(): void
    {
        [$administrador, $senhaAdmin] = $this->user('administrador');
        [$destinatario] = $this->user('servidor');
        [$gestor, $senhaGestor] = $this->user('gestor');
        $this->login($administrador, $senhaAdmin);

        $codigo = 'qa_'.bin2hex(random_bytes(5));
        $this->post(route('roles.store'), ['codigo' => $codigo, 'nome' => 'Perfil QA delegável'])
            ->assertSessionHas('status');
        $perfil = (int) DB::table('perfis')->where('codigo', $codigo)->value('id');
        $this->assertGreaterThan(4, $perfil);
        $this->post(route('roles.grant', $perfil), ['permissao_id' => 220, 'delegavel' => 0])
            ->assertSessionHas('status');

        $justificativa = 'Delegação restrita para teste isolado.';
        $this->post(route('users.link', $destinatario), [
            'perfil_id' => $perfil, 'unidade_id' => 1,
            'vigente_desde' => CarbonImmutable::now(config('fleet.timezone'))->subMinute()->format('Y-m-d\TH:i'),
            'justificativa' => $justificativa,
        ])->assertSessionHas('status');
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $destinatario)->where('perfil_id', $perfil)->value('id');
        $this->assertGreaterThan(0, $vinculo);
        $this->assertSame($justificativa, DB::table('auditoria')->where('evento', 'perfil_atribuido')->where('entidade_id', $vinculo)->value('descricao'));

        $copia = $codigo.'_copy';
        $this->post(route('roles.duplicate', $perfil), [
            'codigo' => $copia, 'nome' => 'Cópia QA delegável',
            'justificativa' => 'Cópia autorizada para isolamento.',
        ])->assertSessionHas('status');
        $perfilCopia = (int) DB::table('perfis')->where('codigo', $copia)->value('id');
        $this->assertGreaterThan(0, $perfilCopia);
        $this->assertSame(1, DB::table('perfil_permissoes')->where('perfil_id', $perfilCopia)->count());

        $ator = (int) DB::table('usuario_perfis')->where('usuario_id', $administrador)->where('perfil_id', 4)->value('id');
        $this->assertNonDelegableProfileRejected($ator, $destinatario);

        $chave = 'qa.frota.store.'.bin2hex(random_bytes(3));
        $this->post(route('technical-routes.store'), [
            'chave' => $chave, 'nome' => 'Gravação de veículo QA',
            'caminho' => '/frota/novo', 'metodo_http' => 'POST', 'modulo_codigo' => 'frota',
            'ativa' => 0, 'visivel_menu' => 0, 'ordem' => 500,
        ])->assertSessionHas('status');
        $this->assertSame(0, (int) DB::table('rotas_sistema')->where('chave', $chave)->value('ativa'));
        $this->get(route('technical-routes.index'))->assertOk();

        $this->post('/sair');
        $this->login($gestor, $senhaGestor);
        $this->get(route('vehicles.create'))->assertOk();
        $this->post(route('vehicles.store'), [])->assertStatus(403);
    }

    public function test_recovery_uses_local_smtp_and_consumes_hashed_token_once_in_mysql(): void
    {
        if (getenv('FLEET_TEST_MAILPIT') !== '1') {
            $this->markTestSkipped('Requer Mailpit descartável no contrato de integração.');
        }
        $smtpPorta = (int) getenv('FLEET_TEST_MAILPIT_SMTP_PORT');
        $apiPorta = (int) getenv('FLEET_TEST_MAILPIT_API_PORT');
        $this->assertGreaterThan(0, $smtpPorta);
        $this->assertGreaterThan(0, $apiPorta);
        config([
            'app.env' => 'testing', 'app.url' => 'https://frota.example.test',
            'fleet.recovery_mail_enabled' => true,
            'fleet.recovery_queue_enabled' => true,
            'fleet.recovery_queue_connection' => 'database',
            'queue.connections.database.driver' => 'database',
            'mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => $smtpPorta,
            'mail.from.address' => 'no-reply@frota.example.test',
        ]);
        Mail::purge('smtp');
        [$usuario] = $this->user('servidor');
        $identificador = DB::table('usuarios')->where('id', $usuario)->value('identificador');
        DB::table('usuarios')->where('id', $usuario)->update(['email' => $identificador.'@local.test']);
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $usuario)->value('id');
        $sessao = DB::table('sessoes')->insertGetId([
            'usuario_id' => $usuario, 'vinculo_ativo_id' => $vinculo,
            'token_hash' => random_bytes(32), 'expira_em' => CarbonImmutable::now('UTC')->addHour(),
        ]);

        $api = 'http://127.0.0.1:'.$apiPorta.'/api/v1/messages';
        $anteriores = json_decode(file_get_contents($api), true, 512, JSON_THROW_ON_ERROR);
        $this->withServerVariables(['HTTP_HOST' => 'host-nao-confiavel.invalid'])
            ->from('/recuperar-acesso')->post('/recuperar-acesso', ['identificador' => $identificador])
            ->assertSessionHas('status', 'Se houver um acesso elegível, você receberá as orientações no e-mail cadastrado.');
        $this->assertSame(1, DB::table('jobs')->where('queue', 'default')->count());
        Artisan::call('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true, '--sleep' => 0, '--tries' => 3]);
        $posteriores = json_decode(file_get_contents($api), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($anteriores['total'] + 1, $posteriores['total']);
        $mensagem = json_decode(file_get_contents('http://127.0.0.1:'.$apiPorta.'/api/v1/message/'.$posteriores['messages'][0]['ID']), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue((bool) preg_match('~https://frota\.example\.test/redefinir-senha\#[0-9a-f]{64}~', $mensagem['HTML']));
        $this->assertFalse(str_contains($mensagem['HTML'], 'host-nao-confiavel.invalid'));
        preg_match('~/redefinir-senha\#([0-9a-f]{64})~', $mensagem['HTML'], $partes);
        $token = $partes[1];
        $registro = DB::table('recuperacoes_senha')->where('usuario_id', $usuario)->first();
        $this->assertSame(32, strlen($registro->token_hash));
        $this->assertSame(hash('sha256', $token, true), $registro->token_hash);
        $this->assertNull($registro->invalidado_em);

        $formulario = $this->get(route('recovery.reset'))->assertOk();
        $this->assertTrue(str_contains($formulario->getContent(), 'name="token"'));
        $novaSenha = 'NovaSenha!'.bin2hex(random_bytes(10));
        $this->post(route('recovery.consume'), [
            'token' => $token,
            'nova_senha' => $novaSenha, 'nova_senha_confirmation' => $novaSenha,
        ])->assertRedirect('/entrar')->assertSessionHas('status');
        $this->assertNotNull(DB::table('recuperacoes_senha')->where('id', $registro->id)->value('usado_em'));
        $this->assertNotNull(DB::table('sessoes')->where('id', $sessao)->value('encerrada_em'));
        $this->assertTrue(password_verify($novaSenha, DB::table('usuarios')->where('id', $usuario)->value('senha_hash')));
        $segundaTentativa = $this->post(route('recovery.consume'), [
            'token' => $token,
            'nova_senha' => $novaSenha, 'nova_senha_confirmation' => $novaSenha,
        ])->assertStatus(422);
        $this->assertTrue(str_contains($segundaTentativa->getContent(), 'Link inválido'));
    }

    private function assertNonDelegableProfileRejected(int $ator, int $destinatario): void
    {
        try {
            app(ProcedureRunner::class)->call('sp_vincular_perfil', [
                $ator, $destinatario, 1, 1,
                CarbonImmutable::now('UTC')->subMinute()->format('Y-m-d H:i:s.u'), null,
                'Tentativa de delegação excessiva.',
            ]);
            $this->fail('A procedure permitiu delegar ações ausentes do vínculo administrador.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', (string) $erro->getCode());
        }
    }

    /** @return array{int, string} */
    private function user(string $perfil): array
    {
        $senha = 'AcessoQA!'.bin2hex(random_bytes(10));
        $usuario = DB::table('usuarios')->insertGetId([
            'unidade_id' => 1, 'identificador' => 'qa-'.bin2hex(random_bytes(8)),
            'nome' => 'Pessoa de teste isolado',
            'senha_hash' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        $perfilId = (int) DB::table('perfis')->where('codigo', $perfil)->value('id');
        DB::table('usuario_perfis')->insert([
            'usuario_id' => $usuario, 'perfil_id' => $perfilId, 'unidade_id' => 1,
            'vigente_desde' => CarbonImmutable::now('UTC')->subMinute(), 'concedido_por' => $usuario,
        ]);

        return [$usuario, $senha];
    }

    private function login(int $usuario, string $senha): void
    {
        $identificador = DB::table('usuarios')->where('id', $usuario)->value('identificador');
        $this->post('/entrar', ['identificador' => $identificador, 'senha' => $senha])->assertRedirect('/painel');
    }
}
