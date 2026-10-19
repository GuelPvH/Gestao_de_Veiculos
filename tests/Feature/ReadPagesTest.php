<?php

namespace Tests\Feature;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReadFixture;
use Tests\Support\TripFixture;
use Tests\TestCase;

class ReadPagesTest extends TestCase
{
    public function test_all_profile_pages_and_allowed_reviews_render_from_authorized_queries(): void
    {
        ReadFixture::create();
        TripFixture::seed();
        $exportacao = getenv('FLEET_UI_EXPORT_DIR');
        if (! $exportacao) {
            $this->withoutVite();
        }
        $paginas = [];
        $medicoes = [];
        $verificacoes = 0;
        $registrar = function (string $perfil, string $url, string $nome) use (&$paginas, &$medicoes, &$verificacoes, $exportacao): void {
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            $inicio = hrtime(true);
            $resposta = $this->get($url);
            $consultas = DB::connection()->getQueryLog();
            DB::connection()->disableQueryLog();
            DB::connection()->flushQueryLog();
            $medicoes[] = ['profile' => $perfil, 'name' => $nome, 'route' => $this->app['router']->currentRouteName(), 'path' => parse_url($url, PHP_URL_PATH), 'query' => parse_url($url, PHP_URL_QUERY), 'status' => $resposta->status(), 'queries' => count($consultas), 'render_ms' => round((hrtime(true) - $inicio) / 1e6, 2), 'sql' => array_column($consultas, 'query')];
            $resposta->assertOk();
            $verificacoes++;
            if ($exportacao) {
                $raiz = realpath(base_path());
                $diretorio = realpath($exportacao);
                if (! $diretorio || str_starts_with($diretorio, $raiz.DIRECTORY_SEPARATOR)) {
                    throw new \RuntimeException('Exportação de teste deve ficar fora do repositório.');
                }
                $arquivo = $diretorio.'/'.$perfil.'-'.$nome.'.html';
                file_put_contents($arquivo, $resposta->getContent());
                chmod($arquivo, 0600);
                $paginas[] = ['profile' => $perfil, 'url' => $url, 'name' => $nome, 'file' => $arquivo];
            }
        };
        $registrar('public', '/', 'login');
        $registrar('public', '/entrar', 'login-route');
        $registrar('public', '/recuperar-acesso', 'recovery');
        $registrar('public', '/redefinir-senha', 'reset');
        $registrar('public', '/ativar-acesso', 'activation');
        $registrar('public', '/confirmar-email', 'verify-email');
        DB::table('pneus')->insert(['id' => 1, 'codigo' => 'QA-PNEU', 'medida' => '195/65R15', 'despesa_aquisicao_id' => 1, 'situacao' => 'estoque']);
        foreach ([1 => 'servidor', 2 => 'gestor', 3 => 'financeiro', 4 => 'administrador'] as $id => $perfil) {
            $vinculo = ReadFixture::profile($id);
            $acesso = app(AccessContext::class);
            $repo = app(FleetReadRepository::class);
            $acoes = app(OperationCatalog::class);
            $registrar($perfil, route('dashboard'), 'dashboard');
            foreach (config('screens') as $codigo => $tela) {
                if (! $acesso->level($tela['module'])) {
                    continue;
                }
                $registrar($perfil, route(($tela['route'] ?? $codigo).'.index'), $codigo.'-index');
                $registrar($perfil, route((config('screens.'.$codigo.'.route') ?? $codigo).'.show', 1), $codigo.'-detail');
                if (($tela['create'] ?? false) && $acesso->can($tela['module'], 'criar', 1, 1)) {
                    $registrar($perfil, route(($tela['route'] ?? $codigo).'.create'), $codigo.'-create');
                }
                foreach ($acoes->allowed($codigo, $repo->record($codigo, 1)) as $acao => $rotulo) {
                    if ($codigo === 'requests' && $acao !== 'edit') {
                        continue;
                    }
                    $registrar($perfil, ($codigo === 'requests' ? route('solicitacoes.'.$acao, 1) : route($codigo.'.operation', ['registro' => 1, 'acao' => $acao])), $codigo.'-'.$acao);
                }
            }
            foreach (['account.index' => 'account', 'notifications.index' => 'notifications', 'reports.index' => 'reports'] as $rota => $nome) {
                $registrar($perfil, route($rota), $nome);
            }
            if ($acesso->level('rastreamento') && $acesso->level('rastreamento', 'ver_localizacao')) {
                $registrar($perfil, route('history.index'), 'history');
            }
            if ($acesso->level('frota')) {
                $registrar($perfil, route('agenda.index', ['semana' => '2026-10-05']), 'agenda');
                $registrar($perfil, route('agenda.show', 1), 'agenda-detail');
            }
            if ($acesso->can('configuracoes')) {
                $registrar($perfil, route('configuration.index'), 'configuration');
            }
            if ($acesso->level('despesas') > 0) {
                $registrar($perfil, route('tyres.index'), 'tyres-index');
                $registrar($perfil, route('tyres.show', 1), 'tyres-detail');
                if ($acesso->can('despesas', 'criar', 1, 1)) {
                    $registrar($perfil, route('tyres.create'), 'tyres-create');
                }
            }
            if ($perfil === 'gestor') {
                $registrar($perfil, route('reports.index', ['modulo' => 'vehicles', 'etapa' => 'preparar']), 'reports-prepare');
                $registrar($perfil, route('reports.index', ['modulo' => 'vehicles', 'etapa' => 'previa']), 'reports-preview');
            }
            config(['fleet.review_enabled' => true]);
            $registrar($perfil, route('review.index'), 'review');
        }
        // Estados operacionais testados com fixture de leitura, sem simular procedures MySQL.
        ReadFixture::profile(1);
        DB::table('vw_solicitacoes_atuais')->where('id', 1)->update(['situacao' => 'rascunho']);
        $registrar('servidor', route('solicitacoes.edit', 1), 'requests-edit');
        ReadFixture::profile(3);
        DB::table('despesas')->where('id', 1)->update(['situacao' => 'registrada']);
        $registrar('financeiro', route('fuel.operation', ['registro' => 1, 'acao' => 'edit']), 'fuel-edit');
        DB::table('pneus')->where('id', 1)->update(['situacao' => 'instalado']);
        DB::table('pneu_instalacoes')->insert(['id' => 1, 'pneu_id' => 1, 'veiculo_id' => 1, 'posicao' => 'DT', 'instalado_em' => '2026-10-11 10:00:00', 'quilometragem_instalacao' => 100]);
        $registrar('financeiro', route('tyres.show', 1), 'tyres-installed');
        ReadFixture::profile(2);
        Storage::fake('local');
        $chave = 'relatorios/'.str_repeat('Q', 40).'.csv';
        Storage::disk('local')->put($chave, "Protocolo\nQA\n");
        DB::table('arquivos')->insert(['id' => 2000, 'enviado_por' => 1, 'chave_armazenamento' => $chave, 'nome_original' => 'relatorio-qa.csv', 'situacao' => 'disponivel']);
        DB::table('exportacoes')->insert(['id' => 2000, 'solicitado_por_vinculo_id' => 10, 'modulo_codigo' => 'frota', 'formato' => 'csv', 'situacao' => 'concluida', 'total_registros' => 1, 'arquivo_id' => 2000]);
        DB::table('exportacao_registros')->insert(['exportacao_id' => 2000, 'ordem' => 1, 'veiculo_id' => 1, 'snapshot' => '{}']);
        DB::table('exportacao_campos')->insert(['exportacao_id' => 2000, 'campo_id' => DB::table('relatorio_campos')->where('modulo_codigo', 'frota')->where('chave', 'placa')->value('id'), 'modulo_codigo' => 'frota', 'ordem' => 1]);
        $registrar('gestor', route('reports.ready', 2000), 'reports-ready');
        foreach (['requests' => ['aguardando_analise', 'ajustes_solicitados', 'aprovada', 'negada', 'cancelada'], 'trips' => ['programada', 'em_andamento', 'concluida', 'cancelada']] as $codigo => $estados) {
            foreach ($estados as $estado) {
                DB::table(config('screens.'.$codigo.'.table'))->where('id', 2)->update(['situacao' => $estado]);
                $registrar('gestor', route((config('screens.'.$codigo.'.route') ?? $codigo).'.show', 2), $codigo.'-'.$estado);
                foreach (app(OperationCatalog::class)->allowed($codigo, app(FleetReadRepository::class)->record($codigo, 2)) as $acao => $rotulo) {
                    if ($codigo === 'requests' && $acao !== 'edit') {
                        continue;
                    }
                    $registrar('gestor', ($codigo === 'requests' ? route('solicitacoes.'.$acao, 2) : route($codigo.'.operation', ['registro' => 2, 'acao' => $acao])), $codigo.'-'.$estado.'-'.$acao);
                }
            }
        }
        ReadFixture::profile(3);
        foreach (config('screens.fines.states') as $estado) {
            DB::table('vw_multas_detalhadas')->where('id', 2)->update(['situacao' => $estado]);
            $registrar('financeiro', route('fines.show', 2), 'fines-'.$estado);
            foreach (app(OperationCatalog::class)->allowed('fines', app(FleetReadRepository::class)->record('fines', 2)) as $acao => $rotulo) {
                $registrar('financeiro', route('fines.operation', ['registro' => 2, 'acao' => $acao]), 'fines-'.$estado.'-'.$acao);
            }
        }
        if ($exportacao) {
            file_put_contents($exportacao.'/pages.json', json_encode(['pages' => $paginas, 'checks' => $verificacoes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        if ($arquivo = getenv('FLEET_PAGE_PERFORMANCE_REPORT')) {
            if (str_starts_with(realpath(dirname($arquivo)).DIRECTORY_SEPARATOR, realpath(base_path()).DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Relatório precisa ficar fora do repositório.');
            }
            file_put_contents($arquivo, json_encode(['source' => 'SQLite read fixtures; auth/session fixture; real Blade render, SQL counts are not remote timings', 'pages' => $medicoes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            chmod($arquivo, 0600);
        }
        $this->assertGreaterThan(65, $verificacoes);
    }
}
