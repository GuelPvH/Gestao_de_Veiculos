<?php

namespace App\Services\Admin;

use App\Support\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * @phpstan-type RouteRequirement array{modulo: string, acao: string}
 * @phpstan-type Endpoint array{nome: string, titulo: string, caminho: string, metodo: string, modulo: string, acao: ?string, acoes: list<RouteRequirement>, visivel_menu: bool}
 */
class TechnicalRouteCatalog
{
    /** @return array<string, Endpoint> */
    private function endpoints(): array
    {
        $endpoints = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $nome = $route->getName();
            if ($nome === null || ! in_array('fleet.enabled', $route->gatherMiddleware(), true)) {
                continue;
            }
            [$prefixo, $operacao] = array_pad(explode('.', $nome, 2), 2, '');
            $tela = config('screens.'.$prefixo);
            $modulo = is_array($tela) ? Value::string($tela['module']) : match ($prefixo) {
                'dashboard', 'review' => 'painel',
                'solicitacoes' => 'solicitacoes',
                'history', 'trackers' => 'rastreamento',
                'agenda', 'drivers' => 'frota',
                'finance', 'tyres' => 'despesas',
                'reports' => 'relatorios',
                'configuration', 'catalogs' => 'configuracoes',
                'notifications' => 'notificacoes',
                'account' => 'conta',
                default => null,
            };
            if ($modulo === null) {
                continue;
            }
            $acoes = $this->requirements($nome, $operacao, $modulo);
            $acao = $acoes[0]['acao'] ?? null;
            $caminho = '/'.ltrim($route->uri(), '/');
            $tituloBase = $this->title($nome, $operacao, $prefixo, $tela);
            foreach (array_intersect(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) as $metodo) {
                $visivelMenu = $metodo === 'GET' && $this->isMenuRoute($nome, is_array($tela));
                $titulo = mb_substr($tituloBase, 0, 120);
                $endpoints[$metodo.' '.$caminho] = [
                    'nome' => $nome,
                    'titulo' => $titulo,
                    'caminho' => $caminho,
                    'metodo' => $metodo,
                    'modulo' => $modulo,
                    'acao' => $acao,
                    'acoes' => $acoes,
                    'visivel_menu' => $visivelMenu,
                ];
            }
        }

        return $endpoints;
    }

    /** @return list<RouteRequirement> */
    private function requirements(string $nome, string $operacao, string $modulo): array
    {
        if (in_array($nome, ['configuration.logo', 'review.index', 'catalogs.suppliers', 'catalogs.section', 'catalogs.store', 'catalogs.update'], true)
            || str_starts_with($nome, 'drivers.')
            || str_starts_with($nome, 'trackers.')
            || in_array($operacao, ['operation', 'perform'], true)) {
            return [];
        }

        return match ($nome) {
            'dashboard', 'reports.export', 'reports.ready', 'reports.download' => [['modulo' => $modulo, 'acao' => 'consultar']],
            'catalogs.index' => [['modulo' => 'configuracoes', 'acao' => 'consultar']],
            'history.index' => [['modulo' => 'rastreamento', 'acao' => 'consultar'], ['modulo' => 'rastreamento', 'acao' => 'ver_localizacao']],
            'monitoring.positions.store' => [['modulo' => 'rastreamento', 'acao' => 'ver_localizacao'], ['modulo' => 'frota', 'acao' => 'gerenciar']],
            'monitoring.manual-positions.store' => [['modulo' => 'rastreamento', 'acao' => 'ver_localizacao']],
            'finance.summary', 'expenses.receipt', 'fuel.receipt', 'expenses.download', 'fuel.download', 'maintenance.download' => [['modulo' => 'despesas', 'acao' => 'consultar'], ['modulo' => 'despesas', 'acao' => 'ver_valores']],
            'fines.download' => [['modulo' => 'multas', 'acao' => 'consultar'], ['modulo' => 'multas', 'acao' => 'ver_valores']],
            'vehicles.documents.download' => [['modulo' => 'frota', 'acao' => 'consultar']],
            'vehicles.documents.store' => [['modulo' => 'frota', 'acao' => 'editar']],
            'configuration.export' => [['modulo' => 'configuracoes', 'acao' => 'consultar'], ['modulo' => 'configuracoes', 'acao' => 'exportar']],
            'users.temporary' => [['modulo' => 'usuarios', 'acao' => 'gerenciar'], ['modulo' => 'usuarios', 'acao' => 'editar']],
            'users.link', 'roles.grant', 'roles.revoke' => [['modulo' => $modulo, 'acao' => 'delegar']],
            'notifications.read', 'notifications.hide', 'users.revoke', 'tyres.install', 'tyres.remove', 'technical-routes.permissions.store', 'technical-routes.permissions.destroy' => [['modulo' => $modulo, 'acao' => 'editar']],
            'tyres.discard' => [['modulo' => 'despesas', 'acao' => 'cancelar']],
            'solicitacoes.send.submit' => [['modulo' => 'solicitacoes', 'acao' => 'enviar']],
            'solicitacoes.revision.submit' => [['modulo' => 'solicitacoes', 'acao' => 'editar']],
            'solicitacoes.approve.submit' => [['modulo' => 'solicitacoes', 'acao' => 'aprovar']],
            'solicitacoes.deny.submit' => [['modulo' => 'solicitacoes', 'acao' => 'negar']],
            'solicitacoes.adjust.submit' => [['modulo' => 'solicitacoes', 'acao' => 'solicitar_ajustes']],
            'solicitacoes.cancel.submit' => [['modulo' => 'solicitacoes', 'acao' => 'cancelar']],
            default => match ($operacao) {
                'index', 'show', 'download' => [['modulo' => $modulo, 'acao' => 'consultar']],
                'create', 'store', 'duplicate' => [['modulo' => $modulo, 'acao' => 'criar']],
                'edit', 'update' => [['modulo' => $modulo, 'acao' => 'editar']],
                default => [],
            },
        };
    }

    private function isMenuRoute(string $nome, bool $isConfiguredScreen): bool
    {
        if (in_array($nome, [
            'dashboard',
            'solicitacoes.index',
            'tyres.index',
            'finance.summary',
            'catalogs.index',
            'catalogs.suppliers',
            'drivers.index',
            'trackers.index',
            'agenda.index',
            'history.index',
            'reports.index',
            'configuration.index',
            'account.index',
            'notifications.index',
        ], true)) {
            return true;
        }

        return $isConfiguredScreen && str_ends_with($nome, '.index');
    }

    private function title(string $nome, string $operacao, string $prefixo, mixed $tela): string
    {
        $area = is_array($tela) ? Value::string($tela['title'] ?? $prefixo) : match ($prefixo) {
            'dashboard' => 'Painel',
            'review' => 'Apresentação',
            'solicitacoes' => 'Solicitações',
            'history' => 'Histórico de trajetos',
            'trackers' => 'Rastreadores',
            'agenda' => 'Agenda de veículos',
            'drivers' => 'Motoristas',
            'finance' => 'Resumo financeiro',
            'tyres' => 'Pneus',
            'reports' => 'Relatórios',
            'configuration' => 'Configurações',
            'catalogs' => 'Cadastros auxiliares',
            'notifications' => 'Notificações',
            'account' => 'Minha conta',
            default => ucfirst($prefixo),
        };
        $specific = match ($nome) {
            'dashboard' => 'Painel principal',
            'review.index' => 'Apresentação de perfis',
            'configuration.logo' => 'Logotipo do sistema',
            'configuration.export' => 'Exportar configurações',
            'catalogs.suppliers' => 'Fornecedores',
            'catalogs.section' => 'Seção de cadastros auxiliares',
            'catalogs.store' => 'Cadastrar item auxiliar',
            'catalogs.update' => 'Atualizar item auxiliar',
            'finance.summary' => 'Resumo financeiro',
            'history.index' => 'Histórico de trajetos',
            'drivers.cnh' => 'Baixar CNH do motorista',
            'trackers.install' => 'Instalar rastreador',
            'trackers.remove' => 'Remover rastreador',
            'monitoring.positions.store' => 'Registrar posição de rastreador',
            'monitoring.manual-positions.store' => 'Registrar posição manual',
            'vehicles.documents.store' => 'Enviar documento de veículo',
            'vehicles.documents.download' => 'Baixar documento de veículo',
            'tyres.install' => 'Instalar pneu',
            'tyres.remove' => 'Remover pneu',
            'tyres.discard' => 'Descartar pneu',
            'users.temporary' => 'Emitir código temporário',
            'users.link' => 'Atribuir vínculo de perfil',
            'users.revoke' => 'Revogar vínculo de perfil',
            'roles.duplicate' => 'Duplicar perfil',
            'roles.grant' => 'Conceder permissão a perfil',
            'roles.revoke' => 'Revogar permissão de perfil',
            'technical-routes.permissions.store' => 'Vincular permissão à rota técnica',
            'technical-routes.permissions.destroy' => 'Remover permissão da rota técnica',
            'notifications.read' => 'Marcar notificação como lida',
            'notifications.hide' => 'Ocultar notificação',
            'reports.export' => 'Exportar relatório',
            'reports.ready' => 'Relatório concluído',
            'reports.download' => 'Baixar arquivo de relatório',
            'solicitacoes.send.submit' => 'Enviar solicitação',
            'solicitacoes.revision.submit' => 'Abrir revisão de solicitação',
            'solicitacoes.approve.submit' => 'Aprovar solicitação',
            'solicitacoes.deny.submit' => 'Negar solicitação',
            'solicitacoes.adjust.submit' => 'Solicitar ajustes em solicitação',
            'solicitacoes.cancel.submit' => 'Cancelar solicitação',
            default => null,
        };
        if ($specific !== null) {
            return $specific;
        }

        return match ($operacao) {
            'index' => $area,
            'create' => $area.' · Formulário de criação',
            'store' => $area.' · Cadastrar',
            'show' => $area.' · Detalhes',
            'edit' => $area.' · Formulário de edição',
            'update' => $area.' · Atualizar',
            'download' => $area.' · Baixar anexo',
            'receipt' => $area.' · Comprovante',
            'operation' => $area.' · Formulário de operação',
            'perform' => $area.' · Executar operação',
            default => $area.' · '.$operacao,
        };
    }

    /** @return array<string, Endpoint> */
    public function all(): array
    {
        $endpoints = $this->endpoints();
        ksort($endpoints, SORT_NATURAL);

        return $endpoints;
    }

    /** @return Endpoint|null */
    public function find(string $chave): ?array
    {
        return $this->endpoints()[$chave] ?? null;
    }

    /** @return array<string, string> */
    public function available(): array
    {
        $modulos = DB::table('modulos')->where('ativo', 1)->pluck('nome', 'codigo');
        $registradas = DB::table('rotas_sistema')->get(['metodo_http', 'caminho'])
            ->mapWithKeys(fn ($rota): array => [Value::string($rota->metodo_http).' '.Value::string($rota->caminho) => true]);
        $opcoes = [];
        foreach ($this->endpoints() as $chave => $rota) {
            if (! isset($registradas[$chave]) && isset($modulos[$rota['modulo']])) {
                $permissao = $rota['acoes'] !== []
                    ? implode(', ', array_map(fn (array $req): string => $req['modulo'].'.'.$req['acao'], $rota['acoes']))
                    : 'verificada pelo controlador';
                $opcoes[$chave] = $chave.' · '.Value::string($modulos[$rota['modulo']]).' · '.$permissao;
            }
        }
        ksort($opcoes, SORT_NATURAL);

        return $opcoes;
    }
}
