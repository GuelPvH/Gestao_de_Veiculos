<?php

namespace Tests\Feature\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\SecurityFixture;
use Tests\TestCase;

class AuthPageAuditTest extends TestCase
{
    public function test_each_authentication_page_and_status_renders_for_each_profile(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00', 'UTC'));
        SecurityFixture::create();
        $measurements = [];
        $visit = function (string $profile, string $path) use (&$measurements): void {
            Auth::forgetGuards();
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            $response = $this->get($path);
            $queries = DB::connection()->getQueryLog();
            DB::connection()->disableQueryLog();
            DB::connection()->flushQueryLog();
            $response->assertOk();
            $measurements[] = ['profile' => $profile, 'path' => $path, 'route' => $this->app['router']->currentRouteName(), 'status' => $response->status(), 'queries' => count($queries), 'sql' => array_column($queries, 'query')];
        };
        foreach (['/', '/entrar', '/recuperar-acesso', '/redefinir-senha', '/ativar-acesso', '/confirmar-email'] as $path) {
            $visit('public', $path);
        }
        foreach ([2 => 'gestor', 3 => 'financeiro', 4 => 'servidor'] as $id => $code) {
            DB::table('perfis')->insert(['id' => $id, 'nome' => $code, 'codigo' => $code]);
            DB::table('usuario_perfis')->insert(['id' => $id, 'usuario_id' => 1, 'perfil_id' => $id, 'unidade_id' => 1]);
            DB::table('vw_vinculos_ativos')->insert(['vinculo_id' => $id, 'usuario_id' => 1, 'perfil_id' => $id, 'unidade_id' => 1]);
        }
        $name = Auth::guard()->getName();
        $this->withSession([$name => 1, 'fleet_session' => ['id' => 1, 'token' => SecurityFixture::TOKEN]]);
        $this->withCredentials()->withCookie(config('session.cookie'), session()->getId());
        foreach ([1 => 'administrador', 2 => 'gestor', 3 => 'financeiro', 4 => 'servidor'] as $id => $code) {
            DB::table('sessoes')->where('id', 1)->update(['vinculo_ativo_id' => $id]);
            foreach (['/acesso-restrito', '/selecionar-perfil', '/alterar-senha', '/seguranca/mfa', '/sessao/status'] as $path) {
                $visit($code, $path);
            }
        }
        if ($file = getenv('FLEET_AUTH_PAGE_REPORT')) {
            if (str_starts_with(realpath(dirname($file)).DIRECTORY_SEPARATOR, realpath(base_path()).DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Relatório precisa ficar fora do repositório.');
            }
            file_put_contents($file, json_encode(['source' => 'SQLite security fixture; real authentication/session services', 'pages' => $measurements], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
