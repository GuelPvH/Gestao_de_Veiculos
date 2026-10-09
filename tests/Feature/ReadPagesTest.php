<?php

namespace Tests\Feature;

use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use Illuminate\Support\Facades\DB;
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
        $verificacoes = 0;
        $registrar = function (string $perfil, string $url, string $nome) use (&$paginas, &$verificacoes, $exportacao): void {
            $resposta = $this->get($url);
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
        $registrar('public', '/recuperar-acesso', 'recovery');
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
                    if ($codigo === 'requests' && $acao !== 'edit') continue;
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
            }
            if ($acesso->can('configuracoes')) {
                $registrar($perfil, route('configuration.index'), 'configuration');
            }
            config(['fleet.review_enabled' => true]);
            $registrar($perfil, route('review.index'), 'review');
        }
        // Estados operacionais testados com fixture de leitura, sem simular procedures MySQL.
        ReadFixture::profile(2);
        foreach (['requests' => ['aguardando_analise', 'ajustes_solicitados', 'aprovada', 'negada', 'cancelada'], 'trips' => ['programada', 'em_andamento', 'concluida', 'cancelada']] as $codigo => $estados) {
            foreach ($estados as $estado) {
                DB::table(config('screens.'.$codigo.'.table'))->where('id', 2)->update(['situacao' => $estado]);
                $registrar('gestor', route((config('screens.'.$codigo.'.route') ?? $codigo).'.show', 2), $codigo.'-'.$estado);
                foreach (app(OperationCatalog::class)->allowed($codigo, app(FleetReadRepository::class)->record($codigo, 2)) as $acao => $rotulo) {
                    if ($codigo === 'requests' && $acao !== 'edit') continue;
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
        $this->assertGreaterThan(65, $verificacoes);
    }
}
