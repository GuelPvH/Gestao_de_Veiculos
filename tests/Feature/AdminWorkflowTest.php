<?php

namespace Tests\Feature;

use App\Services\Auth\ProcedureRunner;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ReadFixture;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ReadFixture::create();
        ReadFixture::profile(4);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
    }

    public function test_admin_stores_user_calling_procedure(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_criar_usuario', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(1, $dados[1]);
                $this->assertSame('novo.usuario', $dados[2]);
                $this->assertSame('Novo Usuario Silva', $dados[3]);
                $this->assertSame('novo@example.com', $dados[4]);
                $this->assertTrue(password_verify('SenhaSegura123!@#', $dados[5]));

                return true;
            })
            ->andReturn([['usuario_id' => 15]]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('users.store'), [
            'unidade_id' => 1,
            'identificador' => 'novo.usuario',
            'nome' => 'Novo Usuario Silva',
            'email' => 'novo@example.com',
            'senha_temporaria' => 'SenhaSegura123!@#',
            'senha_temporaria_confirmation' => 'SenhaSegura123!@#',
        ])->assertRedirect(route('users.show', 15))->assertSessionHas('status');
    }

    public function test_admin_updates_user_with_concurrency_and_audit(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->times(3)
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $usuario = DB::table('usuarios')->where('id', 2)->first();
        $this->assertSame(1, (int) $usuario->versao);

        $this->post(route('users.update', 2), [
            'versao' => 1,
            'unidade_id' => 1,
            'identificador' => 'qa-isolado-2',
            'nome' => 'Conta de Teste 2 Atualizada',
            'email' => 'teste2@example.com',
            'telefone' => '11999998888',
            'ativo' => 1,
            'justificativa' => 'Atualização cadastral autorizada',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');

        $atualizado = DB::table('usuarios')->where('id', 2)->first();
        $this->assertSame('Conta de Teste 2 Atualizada', $atualizado->nome);
        $this->assertSame(2, (int) $atualizado->versao);

        // Stale version rejected
        $this->post(route('users.update', 2), [
            'versao' => 1,
            'unidade_id' => 1,
            'identificador' => 'qa-isolado-2',
            'nome' => 'Outra Tentativa',
            'email' => 'teste2@example.com',
            'telefone' => '11999998888',
            'ativo' => 1,
            'justificativa' => 'Tentativa desatualizada',
        ])->assertSessionHasErrors('versao');
    }

    public function test_admin_links_profile_and_revokes_link_calling_procedures(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_vincular_perfil', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(2, $dados[1]);
                $this->assertSame(1, $dados[2]);
                $this->assertSame(1, $dados[3]);

                return true;
            })
            ->andReturn([['vinculo_id' => 99]]);

        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_desativar_vinculo', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(20, $dados[1]);
                $this->assertSame('Revogação por transferência de função', $dados[2]);

                return true;
            })
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        // Link profile
        $this->post(route('users.link', 2), [
            'perfil_id' => 1,
            'unidade_id' => 1,
            'vigente_desde' => '2026-10-05T08:00',
            'vigente_ate' => '2026-12-31T18:00',
            'justificativa' => 'Atribuição periódica de perfil',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');

        // Revoke link
        DB::table('usuario_perfis')->where('id', 20)->update(['ativo' => 1]);
        $this->post(route('users.revoke', 2), [
            'vinculo_id' => 20,
            'motivo' => 'Revogação por transferência de função',
            'confirmar_revogacao' => '1',
        ])->assertRedirect(route('users.show', 2))->assertSessionHas('status');
    }

    public function test_admin_stores_updates_and_duplicates_role(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')
            ->withArgs(fn ($nome) => in_array($nome, ['sp_auditar', 'sp_exigir_permissao'], true))
            ->andReturn([]);
        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_duplicar_perfil', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(1, $dados[1]);
                $this->assertSame('perfil_duplicado', $dados[2]);
                $this->assertSame('Perfil Duplicado de Teste', $dados[3]);

                return true;
            })
            ->andReturn([['perfil_id' => 77]]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        // Store role
        $this->post(route('roles.store'), [
            'codigo' => 'novo_perfil',
            'nome' => 'Novo Perfil Personalizado',
            'descricao' => 'Descrição do novo perfil',
        ])->assertRedirect()->assertSessionHas('status');

        $novoPerfil = DB::table('perfis')->where('codigo', 'novo_perfil')->first();
        $this->assertNotNull($novoPerfil);
        $this->assertSame('Novo Perfil Personalizado', $novoPerfil->nome);

        // Update role
        $this->post(route('roles.update', $novoPerfil->id), [
            'atualizado_em' => (string) $novoPerfil->atualizado_em,
            'nome' => 'Novo Perfil Renomeado',
            'descricao' => 'Descrição atualizada',
            'ativo' => 1,
            'justificativa' => 'Atualização de nomenclatura autorizada',
        ])->assertRedirect(route('roles.show', $novoPerfil->id))->assertSessionHas('status');

        // Duplicate role
        $this->post(route('roles.duplicate', 1), [
            'codigo' => 'perfil_duplicado',
            'nome' => 'Perfil Duplicado de Teste',
            'justificativa' => 'Criação de variação operacional',
        ])->assertRedirect(route('roles.show', 77))->assertSessionHas('status');
    }

    public function test_admin_grants_and_revokes_permission_on_role(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->once()
            ->withArgs(function ($nome, $dados): bool {
                $this->assertSame('sp_conceder_permissao', $nome);
                $this->assertSame(10, $dados[0]);
                $this->assertSame(1, $dados[1]);
                $this->assertSame(168, $dados[2]);
                $this->assertSame(1, $dados[3]);

                return true;
            })
            ->andReturn([]);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $permissao = DB::table('permissoes')->where('id', 168)->first();
        $this->assertNotNull($permissao);

        // Grant
        $this->post(route('roles.grant', 1), [
            'permissao_id' => 168,
            'delegavel' => 1,
        ])->assertRedirect(route('roles.show', 1))->assertSessionHas('status');

        // Revoke
        DB::table('perfil_permissoes')->insertOrIgnore([
            'perfil_id' => 1,
            'permissao_id' => 168,
            'delegavel' => 1,
        ]);
        $this->post(route('roles.revoke', 1), [
            'permissao_id' => 168,
            'confirmar_revogacao' => '1',
        ])->assertRedirect(route('roles.show', 1))->assertSessionHas('status');
    }

    public function test_admin_updates_technical_route_and_protects_critical_route(): void
    {
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldReceive('call')->twice()
            ->withArgs(fn ($nome) => in_array($nome, ['sp_exigir_permissao', 'sp_auditar'], true))
            ->andReturn([]);
        app()->instance(ProcedureRunner::class, $procedimentos);

        $rota = DB::table('rotas_sistema')->where('id', 1)->first();
        $this->assertNotNull($rota);

        // Normal update
        $this->post(route('technical-routes.update', 1), [
            'nome' => 'Nome Rota Atualizado',
            'descricao' => 'Nova descrição técnica',
            'ativa' => 1,
            'visivel_menu' => 1,
            'ordem' => 15,
            'justificativa' => 'Ajuste de ordem de exibição',
        ])->assertRedirect(route('technical-routes.show', 1))->assertSessionHas('status');

        // Protected route cannot be disabled
        DB::table('rotas_sistema')->where('id', 1)->update(['protegida' => 1]);
        $this->post(route('technical-routes.update', 1), [
            'nome' => 'Nome Rota Protegida',
            'descricao' => 'Tentativa de desativação',
            'ativa' => 0,
            'visivel_menu' => 1,
            'ordem' => 15,
            'justificativa' => 'Desativação indevida de rota protegida',
        ])->assertSessionHasErrors('ativa');
    }

    public function test_non_admin_cannot_execute_admin_actions(): void
    {
        ReadFixture::profile(1); // Profile 1: Servidor (sem permissão administrativa)
        $procedimentos = Mockery::mock(ProcedureRunner::class);
        $procedimentos->shouldNotReceive('call');
        app()->instance(ProcedureRunner::class, $procedimentos);

        $this->post(route('users.store'), [
            'unidade_id' => 1,
            'identificador' => 'invasor',
            'nome' => 'Invasor',
            'email' => 'invasor@example.com',
            'senha_temporaria' => 'SenhaInvalida123!@#',
            'senha_temporaria_confirmation' => 'SenhaInvalida123!@#',
        ])->assertForbidden();

        $this->post(route('roles.store'), [
            'codigo' => 'perfil_proibido',
            'nome' => 'Perfil Proibido',
        ])->assertForbidden();
    }
}
