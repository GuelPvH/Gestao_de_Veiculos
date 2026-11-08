<?php

namespace App\Services\Admin;

use App\Support\Value;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** @phpstan-type Endpoint array{nome: string, caminho: string, metodo: string, modulo: string, acao: ?string} */
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
                'dashboard' => 'painel',
                'solicitacoes' => 'solicitacoes',
                'history' => 'rastreamento',
                'agenda' => 'frota',
                'tyres' => 'despesas',
                'reports' => 'relatorios',
                'configuration' => 'configuracoes',
                'notifications' => 'notificacoes',
                'account' => 'conta',
                default => null,
            };
            if ($modulo === null) {
                continue;
            }
            $acao = $this->action($nome, $operacao);
            $caminho = '/'.ltrim($route->uri(), '/');
            foreach (array_intersect(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) as $metodo) {
                $endpoints[$metodo.' '.$caminho] = compact('nome', 'caminho', 'metodo', 'modulo', 'acao');
            }
        }

        return $endpoints;
    }

    private function action(string $nome, string $operacao): ?string
    {
        return match ($nome) {
            'dashboard', 'reports.export', 'reports.ready', 'reports.download' => 'consultar',
            'history.index' => 'ver_localizacao',
            'users.link', 'roles.grant', 'roles.revoke' => 'delegar',
            'configuration.export' => 'exportar',
            'users.temporary' => 'gerenciar',
            'notifications.read', 'notifications.hide', 'users.revoke', 'tyres.install', 'tyres.remove' => 'editar',
            'tyres.discard' => 'cancelar',
            'solicitacoes.send.submit' => 'enviar',
            'solicitacoes.revision.submit' => 'editar',
            'solicitacoes.approve.submit' => 'aprovar',
            'solicitacoes.deny.submit' => 'negar',
            'solicitacoes.adjust.submit' => 'solicitar_ajustes',
            default => match ($operacao) {
                'index', 'show', 'download' => 'consultar',
                'create', 'store', 'duplicate' => 'criar',
                'edit', 'update' => 'editar',
                // Shared templates dispatch different actions based on the request.
                default => null,
            },
        };
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
                $permissao = $rota['acao'] ?? 'ação conforme a operação';
                $opcoes[$chave] = $chave.' · '.Value::string($modulos[$rota['modulo']]).' · '.$permissao;
            }
        }
        ksort($opcoes, SORT_NATURAL);

        return $opcoes;
    }
}
