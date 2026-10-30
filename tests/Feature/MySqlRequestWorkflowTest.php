<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Contratos com procedures e triggers reais, exclusivamente no MySQL descartável. */
class MySqlRequestWorkflowTest extends TestCase
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

    private function user(string $perfil, int $unidade = 1): array
    {
        $senha = 'AcessoQA!'.bin2hex(random_bytes(10));
        $id = DB::table('usuarios')->insertGetId([
            'unidade_id' => $unidade, 'identificador' => 'qa-requests-'.bin2hex(random_bytes(8)),
            'nome' => 'Pessoa de teste de solicitações',
            'email' => 'qa-requests-'.bin2hex(random_bytes(8)).'@local.test',
            'estado_identidade' => 'ativa', 'deve_trocar_senha' => 0,
            'email_verificado_em' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.u'),
            'senha_hash' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        DB::table('usuario_perfis')->insert([
            'usuario_id' => $id, 'perfil_id' => DB::table('perfis')->where('codigo', $perfil)->value('id'),
            'unidade_id' => $unidade, 'vigente_desde' => CarbonImmutable::now('UTC')->subMinute(), 'concedido_por' => $id,
        ]);

        return [$id, $senha];
    }

    private function scopedManager(): array
    {
        // O gestor padrão tem alcance de órgão. Este perfil real limita somente seleção/aprovação.
        $codigo = 'qa_requests_unit_'.bin2hex(random_bytes(4));
        $perfil = DB::table('perfis')->insertGetId(['codigo' => $codigo, 'nome' => 'Gestor QA de unidade']);
        $base = (int) DB::table('perfis')->where('codigo', 'gestor')->value('id');
        $permissoes = DB::table('perfil_permissoes')->where('perfil_id', $base)->pluck('permissao_id');
        foreach ($permissoes as $permissao) {
            $permissao = match ((int) $permissao) {
                18 => 17, 51 => 50, default => (int) $permissao
            };
            DB::table('perfil_permissoes')->insert(['perfil_id' => $perfil, 'permissao_id' => $permissao, 'delegavel' => 0]);
        }

        return $this->user($codigo);
    }

    private function login(int $usuario, string $senha): void
    {
        $this->post('/entrar', ['identificador' => DB::table('usuarios')->where('id', $usuario)->value('identificador'), 'senha' => $senha])->assertRedirect('/painel');
    }

    private function driver(int $usuario, array $extras = []): void
    {
        DB::table('motoristas')->insert($extras + [
            'usuario_id' => $usuario, 'numero_cnh' => bin2hex(random_bytes(8)), 'categoria_cnh' => 'B',
            'categorias_autorizadas' => 'B', 'validade_cnh' => CarbonImmutable::now('UTC')->addYear()->toDateString(),
        ]);
    }

    private function vehicle(int $autor, int $unidade = 1, array $extras = []): int
    {
        return DB::table('veiculos')->insertGetId($extras + [
            'unidade_id' => $unidade, 'categoria_id' => 1, 'nome' => 'Veículo QA de solicitações',
            'placa' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 7)), 'capacidade' => 4, 'criado_por' => $autor,
        ]);
    }

    private function payload(int $veiculo, array $extras = []): array
    {
        $local = CarbonImmutable::now(config('fleet.timezone'));

        return $extras + [
            'finalidade' => 'Inspeção QA de contrato', 'origem' => 'Sede', 'destino' => 'Unidade de teste',
            'origem_latitude' => -8.76, 'origem_longitude' => -63.90,
            'destino_latitude' => -8.77, 'destino_longitude' => -63.91,
            'saida_prevista' => $local->addHours(2)->format('Y-m-d\TH:i'),
            'retorno_previsto' => $local->addHours(4)->format('Y-m-d\TH:i'),
            'quantidade_passageiros' => 1, 'passageiros' => 'Pessoa QA', 'necessita_motorista' => '1',
            'veiculo_pretendido_id' => $veiculo,
        ];
    }

    private function createRequest(int $usuario, int $veiculo, array $extras = []): int
    {
        $this->post(route('solicitacoes.store'), $this->payload($veiculo, $extras))->assertRedirectContains('/solicitacoes/')->assertSessionHasNoErrors()->assertSessionHas('status');

        return (int) DB::table('solicitacoes')->where('solicitante_id', $usuario)->orderByDesc('id')->value('id');
    }

    private function sendRequest(int $solicitacao): void
    {
        $this->post(route('solicitacoes.send.submit', $solicitacao), ['versao' => 2])->assertSessionHasNoErrors()->assertSessionHas('status');
    }

    private function assertProcedureDenied(array $parametros): void
    {
        try {
            app(ProcedureRunner::class)->call('sp_aprovar_solicitacao', $parametros);
            $this->fail('A aprovação indevida não foi recusada.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', (string) $erro->getCode());
        }
    }

    public function test_non_driver_requester_keeps_driver_pending_until_authorized_approval(): void
    {
        [$solicitante, $senha] = $this->user('servidor');
        [$gestor, $senhaGestor] = $this->user('gestor');
        [$condutor, $senhaCondutor] = $this->user('servidor');
        $this->driver($condutor);
        $veiculo = $this->vehicle($gestor);
        $this->login($solicitante, $senha);
        $solicitacao = $this->createRequest($solicitante, $veiculo);
        $this->assertGreaterThan(0, $solicitacao);
        $revisao = DB::table('solicitacao_revisoes')->where('solicitacao_id', $solicitacao)->first();
        $this->assertNull($revisao->motorista_sugerido_id);
        $this->assertSame(1, (int) $revisao->necessita_motorista);
        $this->assertEquals(-8.76, (float) $revisao->origem_latitude);
        $this->assertEquals(-63.91, (float) $revisao->destino_longitude);
        $this->assertSame(1, DB::table('solicitacao_passageiros')->where('revisao_id', $revisao->id)->count());
        $this->sendRequest($solicitacao);
        $this->post('/sair');
        $this->login($gestor, $senhaGestor);
        $this->get(route('solicitacoes.show', $solicitacao))->assertOk()->assertSee('Motorista confirmado');
        $this->post(route('solicitacoes.approve.submit', $solicitacao), [
            'versao' => 3, 'veiculo_confirmado_id' => $veiculo, 'motorista_confirmado_id' => $condutor,
            'justificativa' => 'Condutor e disponibilidade conferidos.',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame('aprovada', DB::table('solicitacoes')->where('id', $solicitacao)->value('situacao'));
        $this->assertSame($condutor, (int) DB::table('viagens')->where('revisao_id', $revisao->id)->value('motorista_id'));
        $viagem = (int) DB::table('viagens')->where('revisao_id', $revisao->id)->value('id');
        $this->post('/sair');
        $this->login($condutor, $senhaCondutor);
        $this->get(route('trips.show', $viagem))->assertOk()->assertSee('Registrar saída');
    }

    public function test_uploaded_attachment_is_linked_with_coordinates_before_creation_commits(): void
    {
        [$solicitante, $senha] = $this->user('servidor');
        $veiculo = $this->vehicle($solicitante);
        $this->login($solicitante, $senha);
        Storage::fake('local');

        $this->post(route('solicitacoes.store'), $this->payload($veiculo, [
            'anexo' => UploadedFile::fake()->create('autorizacao.pdf', 20, 'application/pdf'),
        ]))->assertRedirectContains('/solicitacoes/')->assertSessionHasNoErrors();

        $solicitacao = DB::table('solicitacoes')->where('solicitante_id', $solicitante)->orderByDesc('id')->first();
        $this->assertNotNull($solicitacao);
        $revisao = DB::table('solicitacao_revisoes')->where('id', $solicitacao->revisao_atual_id)->first();
        $this->assertNotNull($revisao);
        $anexo = DB::table('anexos')->where('revisao_id', $revisao->id)->first();
        $this->assertNotNull($anexo);
        $arquivo = DB::table('arquivos')->where('id', $anexo->arquivo_id)->first();
        $this->assertNotNull($arquivo);

        $this->assertSame($solicitante, (int) $arquivo->enviado_por);
        $this->assertEquals(-8.76, (float) $revisao->origem_latitude);
        $this->assertEquals(-63.91, (float) $revisao->destino_longitude);
        Storage::disk('local')->assertExists($arquivo->chave_armazenamento);
    }

    public function test_self_driver_requires_active_license_for_category_and_entire_period(): void
    {
        [$solicitante, $senha] = $this->user('servidor');
        $veiculo = $this->vehicle($solicitante);
        $this->login($solicitante, $senha);
        $this->from(route('solicitacoes.create'))->post(route('solicitacoes.store'), $this->payload($veiculo, ['necessita_motorista' => '0']))->assertRedirect(route('solicitacoes.create'))->assertSessionHasErrors('necessita_motorista');
        $this->assertSame(0, DB::table('solicitacoes')->where('solicitante_id', $solicitante)->count());
        $this->driver($solicitante, ['categorias_autorizadas' => 'A']);
        $this->from(route('solicitacoes.create'))->post(route('solicitacoes.store'), $this->payload($veiculo, ['necessita_motorista' => '0']))->assertRedirect(route('solicitacoes.create'))->assertSessionHasErrors('necessita_motorista');
        DB::table('motoristas')->where('usuario_id', $solicitante)->update(['categorias_autorizadas' => 'B', 'validade_cnh' => CarbonImmutable::now('UTC')->subDay()->toDateString()]);
        $this->from(route('solicitacoes.create'))->post(route('solicitacoes.store'), $this->payload($veiculo, ['necessita_motorista' => '0']))->assertRedirect(route('solicitacoes.create'))->assertSessionHasErrors('necessita_motorista');
        DB::table('motoristas')->where('usuario_id', $solicitante)->update(['validade_cnh' => CarbonImmutable::now('UTC')->addYear()->toDateString()]);
        $solicitacao = $this->createRequest($solicitante, $veiculo, ['necessita_motorista' => '0']);
        $this->assertSame($solicitante, (int) DB::table('solicitacao_revisoes')->where('solicitacao_id', $solicitacao)->value('motorista_sugerido_id'));
        $this->patch(route('solicitacoes.update', $solicitacao), $this->payload($veiculo, ['versao' => 2]))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('solicitacao_revisoes')->where('solicitacao_id', $solicitacao)->value('motorista_sugerido_id'));
        $this->patch(route('solicitacoes.update', $solicitacao), $this->payload($veiculo, ['versao' => 2]))->assertSessionHasErrors('operacao');
        $this->assertSame(3, (int) DB::table('solicitacoes')->where('id', $solicitacao)->value('versao'));
    }

    public function test_creation_rolls_back_header_revision_and_events_when_passenger_stage_fails(): void
    {
        [$solicitante] = $this->user('servidor');
        $veiculo = $this->vehicle($solicitante);
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $solicitante)->value('id');
        $contagens = [];
        foreach (['solicitacoes', 'solicitacao_revisoes', 'solicitacao_passageiros', 'solicitacao_eventos', 'auditoria'] as $tabela) {
            $contagens[$tabela] = DB::table($tabela)->count();
        }
        try {
            // O patch insere cabeçalho/revisão antes de conferir a lista; o handler deve reverter tudo.
            app(ProcedureRunner::class)->call('sp_criar_solicitacao_completa', [
                $vinculo, 'Teste de rollback', 'Sede', 'Unidade QA',
                CarbonImmutable::now('UTC')->addHours(2)->format('Y-m-d H:i:s.u'),
                CarbonImmutable::now('UTC')->addHours(4)->format('Y-m-d H:i:s.u'),
                null, 1, 1, $veiculo, null, '["Pessoa 1","Pessoa 2"]',
                -8.76, -63.90, -8.77, -63.91, null,
            ]);
            $this->fail('A lista inválida não foi recusada.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', (string) $erro->getCode());
        }
        foreach ($contagens as $tabela => $quantidade) {
            $this->assertSame($quantidade, DB::table($tabela)->count(), 'Resíduo indevido em '.$tabela);
        }
    }

    public function test_creation_rolls_back_all_rows_when_attachment_cannot_be_linked(): void
    {
        [$solicitante] = $this->user('servidor');
        [$outroUsuario] = $this->user('servidor');
        $veiculo = $this->vehicle($solicitante);
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $solicitante)->value('id');
        $arquivo = DB::table('arquivos')->insertGetId([
            'enviado_por' => $outroUsuario,
            'chave_armazenamento' => 'anexos/'.bin2hex(random_bytes(20)).'.pdf',
            'nome_original' => 'autorizacao.pdf',
            'tipo_mime' => 'application/pdf',
            'tamanho_bytes' => 20,
            'sha256' => hash('sha256', random_bytes(20), true),
            'situacao' => 'disponivel',
        ]);
        $antes = [];
        foreach (['solicitacoes', 'solicitacao_revisoes', 'solicitacao_passageiros', 'solicitacao_eventos', 'auditoria', 'anexos'] as $tabela) {
            $antes[$tabela] = DB::table($tabela)->count();
        }
        $dados = $this->payload($veiculo);

        try {
            app(ProcedureRunner::class)->call('sp_criar_solicitacao_completa', [
                $vinculo, $dados['finalidade'], $dados['origem'], $dados['destino'],
                CarbonImmutable::parse($dados['saida_prevista'], config('fleet.timezone'))->utc()->format('Y-m-d H:i:s.u'),
                CarbonImmutable::parse($dados['retorno_previsto'], config('fleet.timezone'))->utc()->format('Y-m-d H:i:s.u'),
                null, $dados['quantidade_passageiros'], 1, $veiculo, null, '["Pessoa QA"]',
                $dados['origem_latitude'], $dados['origem_longitude'], $dados['destino_latitude'], $dados['destino_longitude'], $arquivo,
            ]);
            $this->fail('O anexo de outro usuário foi aceito.');
        } catch (\PDOException $erro) {
            $this->assertSame('45000', (string) $erro->getCode());
        }

        foreach ($antes as $tabela => $quantidade) {
            $this->assertSame($quantidade, DB::table($tabela)->count(), 'Resíduo indevido em '.$tabela);
        }
    }

    public function test_approval_enforces_driver_and_vehicle_unit_at_http_and_mysql_boundaries(): void
    {
        [$solicitante, $senha] = $this->user('servidor');
        [$gestor, $senhaGestor] = $this->scopedManager();
        [$condutor] = $this->user('servidor');
        $this->driver($condutor);
        $unidade = DB::table('unidades')->insertGetId(['codigo' => 'QA-'.bin2hex(random_bytes(4)), 'nome' => 'Outra unidade QA']);
        [$externo] = $this->user('servidor', $unidade);
        $this->driver($externo);
        $veiculo = $this->vehicle($gestor);
        $veiculoExterno = $this->vehicle($gestor, $unidade);
        $this->login($solicitante, $senha);
        $solicitacao = $this->createRequest($solicitante, $veiculo);
        $this->sendRequest($solicitacao);
        $this->post('/sair');
        $this->login($gestor, $senhaGestor);
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $gestor)->value('id');
        $this->post(route('solicitacoes.approve.submit', $solicitacao), [
            'versao' => 3, 'veiculo_confirmado_id' => $veiculoExterno, 'motorista_confirmado_id' => $externo, 'justificativa' => 'Teste de escopo.',
        ])->assertSessionHasErrors(['veiculo_confirmado_id', 'motorista_confirmado_id']);
        $this->assertProcedureDenied([$vinculo, $solicitacao, 3, $veiculo, $externo, 'Teste de escopo de condutor.']);
        $this->assertProcedureDenied([$vinculo, $solicitacao, 3, $veiculoExterno, $condutor, 'Teste de escopo de veículo.']);
        $this->assertSame('aguardando_analise', DB::table('solicitacoes')->where('id', $solicitacao)->value('situacao'));
        $this->assertSame(0, DB::table('reservas')->where('veiculo_id', $veiculo)->count());
    }

    public function test_ineligible_driver_or_vehicle_cannot_be_reserved_and_state_is_preserved(): void
    {
        [$solicitante, $senha] = $this->user('servidor');
        [$gestor] = $this->user('gestor');
        [$condutor] = $this->user('servidor');
        $this->driver($condutor, ['categorias_autorizadas' => 'A']);
        $veiculo = $this->vehicle($gestor);
        $this->login($solicitante, $senha);
        $solicitacao = $this->createRequest($solicitante, $veiculo);
        $this->sendRequest($solicitacao);
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $gestor)->value('id');
        $this->assertProcedureDenied([$vinculo, $solicitacao, 3, $veiculo, $condutor, 'Categoria incompatível.']);
        DB::table('motoristas')->where('usuario_id', $condutor)->update(['categorias_autorizadas' => 'B', 'validade_cnh' => CarbonImmutable::now('UTC')->subDay()->toDateString()]);
        $this->assertProcedureDenied([$vinculo, $solicitacao, 3, $veiculo, $condutor, 'CNH vencida.']);
        DB::table('motoristas')->where('usuario_id', $condutor)->update(['validade_cnh' => CarbonImmutable::now('UTC')->addYear()->toDateString()]);
        DB::table('veiculos')->where('id', $veiculo)->update(['situacao_cadastro' => 'inativo']);
        $this->assertProcedureDenied([$vinculo, $solicitacao, 3, $veiculo, $condutor, 'Veículo inativo.']);
        $this->assertSame('aguardando_analise', DB::table('solicitacoes')->where('id', $solicitacao)->value('situacao'));
        $this->assertSame(3, (int) DB::table('solicitacoes')->where('id', $solicitacao)->value('versao'));
        $this->assertSame(0, DB::table('reservas')->where('veiculo_id', $veiculo)->count());
    }

    public function test_two_concurrent_approvals_create_exactly_one_trip_and_reservation(): void
    {
        [$solicitante, $senha] = $this->user('servidor');
        [$gestor] = $this->user('gestor');
        [$condutor] = $this->user('servidor');
        $this->driver($condutor);
        $veiculo = $this->vehicle($gestor);
        $this->login($solicitante, $senha);
        $solicitacao = $this->createRequest($solicitante, $veiculo);
        $this->sendRequest($solicitacao);
        $vinculo = (int) DB::table('usuario_perfis')->where('usuario_id', $gestor)->value('id');
        $parametros = json_encode([$vinculo, $solicitacao, 3, $veiculo, $condutor, 'Confirmação concorrente QA.'], JSON_THROW_ON_ERROR);
        $codigo = <<<'PHP'
$config = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET time_zone='+00:00'");
while (microtime(true) < (float) $argv[3]) { usleep(1000); }
try {
    $call = $pdo->prepare('CALL sp_aprovar_solicitacao(?,?,?,?,?,?)');
    $call->execute(json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR));
    do { if ($call->columnCount() > 0) { $call->fetchAll(); } } while ($call->nextRowset());
    $call->closeCursor();
    echo 'ok';
} catch (PDOException $error) { echo (string) $error->getCode(); }
PHP;
        $processos = [];
        $inicio = (string) (microtime(true) + 0.3);
        try {
            for ($indice = 0; $indice < 2; $indice++) {
                $pipes = [];
                $processo = proc_open([PHP_BINARY, '-r', $codigo, getenv('FLEET_MYSQL_TEST_CONFIG'), $parametros, $inicio], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($processo);
                $processos[] = [$processo, $pipes];
            }
            $resultados = [];
            foreach ($processos as [$processo, $pipes]) {
                $resultados[] = stream_get_contents($pipes[1]);
                $this->assertSame('', stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($processo));
            }
            $processos = [];
            sort($resultados);
            $this->assertSame(['45000', 'ok'], $resultados);
        } finally {
            foreach ($processos as [$processo, $pipes]) {
                if (is_resource($processo)) {
                    proc_terminate($processo);
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($processo)) {
                    proc_close($processo);
                }
            }
        }
        $revisao = (int) DB::table('solicitacoes')->where('id', $solicitacao)->value('revisao_atual_id');
        $this->assertSame(1, DB::table('viagens')->where('revisao_id', $revisao)->count());
        $this->assertSame(1, DB::table('reservas')->where('revisao_id', $revisao)->where('situacao', 'ativa')->count());
        $this->assertSame(1, DB::table('solicitacao_eventos')->where('solicitacao_id', $solicitacao)->where('tipo', 'aprovada')->count());
        $this->assertSame(4, (int) DB::table('solicitacoes')->where('id', $solicitacao)->value('versao'));
    }
}
