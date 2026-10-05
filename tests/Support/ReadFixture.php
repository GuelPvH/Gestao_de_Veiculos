<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\Auth\FleetSession;
use App\Services\Authorization\AccessContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use stdClass;

/** Fixture de leitura: não simula validade de constraints, views, procedures ou triggers MySQL. */
class ReadFixture
{
    public static function create(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $contrato = json_decode(file_get_contents(__DIR__.'/schema.json'), true, 512, JSON_THROW_ON_ERROR);
        if (hash_file('sha256', database_path('sql/frota_pf_mysql.sql')) !== $contrato['source_sha256']) {
            throw new \RuntimeException('Regenere o contrato de leitura após conferir o SQL canônico.');
        }
        foreach ($contrato['tables'] as $tabela => $colunas) {
            $ddl = [];
            foreach ($colunas as $coluna => $tipo) {
                $ddl[] = '"'.$coluna.'" '.($coluna === 'id' ? 'INTEGER PRIMARY KEY' : $tipo['type']).($tipo['default'] !== null ? ' DEFAULT '.$tipo['default'] : '');
            }
            DB::statement('CREATE TABLE "'.$tabela.'" ('.implode(',', $ddl).')');
        }
        foreach ($contrato['views'] as $nome => $colunas) {
            DB::statement('CREATE TABLE "'.$nome.'" ('.implode(',', array_map(fn ($coluna) => '"'.$coluna.'" '.(preg_match('/(^id$|_id$|^nivel_alcance$|^delegavel$)/', $coluna) ? 'INTEGER' : 'TEXT'), $colunas)).')');
        }
        $sql = explode('-- Views não autenticam', file_get_contents(database_path('sql/frota_pf_mysql.sql')))[0];
        preg_match_all('/INSERT INTO (\w+) \(([^)]+)\) VALUES\s*([\s\S]+?);/', $sql, $insercoes, PREG_SET_ORDER);
        foreach ($insercoes as $insercao) {
            DB::unprepared($insercao[0]);
        }
        for ($id = 1; $id <= 3; $id++) {
            User::forceCreate(['id' => $id, 'identificador' => 'qa-isolado-'.$id, 'nome' => 'Conta de teste '.$id, 'unidade_id' => $id === 3 ? 2 : 1, 'senha_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 4]), 'ativo' => 1, 'deve_trocar_senha' => 0]);
        }
        DB::table('unidades')->insert(['id' => 2, 'codigo' => 'QA', 'nome' => 'Unidade de teste', 'ativa' => 1]);
        foreach (config('screens') as $codigo => $tela) {
            if (in_array($codigo, ['users', 'roles'], true)) {
                continue;
            }
            for ($id = 1; $id <= 3; $id++) {
                $dados = ['id' => $id];
                if ($codigo === 'monitoring') {
                    $dados = ['veiculo_id' => $id];
                }
                foreach ($contrato['views'][$tela['table']] ?? array_keys($contrato['tables'][$tela['table']]) as $coluna) {
                    if (isset($dados[$coluna])) {
                        continue;
                    }
                    $dados[$coluna] = match ($coluna) {
                        'usuario_id','solicitante_id','responsavel_id','criado_por','ator_usuario_id','motorista_id' => $id,
                        'veiculo_id','revisao_id' => $id,
                        'unidade_id' => $id === 3 ? 2 : 1,
                        'categoria_id' => 1,
                        'protocolo' => 'QA-'.strtoupper($codigo).'-'.$id,
                        'placa' => 'ABC1D2'.$id,
                        'nome','veiculo' => 'Veículo de teste '.$id,
                        'solicitante','responsavel','motorista','ator_nome_snapshot' => 'Conta de teste '.$id,
                        'origem' => 'Sede','destino' => 'Unidade operacional','finalidade','descricao' => 'Atividade de teste isolado',
                        'assunto' => 'Solicitação de atendimento '.$id,
                        'situacao' => 'programada','situacao_cadastro' => 'ativo', 'situacao_operacional' => 'disponivel','situacao_comunicacao' => 'transmitindo',
                        'ativo','ativa','implementada','protegida','quantidade_passageiros','capacidade' => 1,
                        'valor','preco_unitario','valor_pago' => 12345.67,
                        'latitude' => -8.7612345,'longitude' => -63.9023456,
                        'quilometragem','quilometragem_atual','quilometragem_saida' => 25000,
                        'data_despesa','data_vencimento' => '2026-10-05',
                        'criado_em','atualizado_em','saida_prevista','inicio_previsto','ocorrido_em','capturado_em' => '2026-10-05 12:00:00',
                        'retorno_previsto','fim_previsto' => '2026-10-05 18:00:00',
                        'perfil_nome_snapshot' => 'Perfil de teste','evento' => 'consulta','entidade' => 'veiculos',
                        'metodo_http' => 'GET','caminho' => '/rota-de-teste-'.$id,
                        default => null,
                    };
                }
                if ($codigo === 'requests') {
                    $dados['situacao'] = 'aguardando_analise';
                }
                if ($codigo === 'fines') {
                    $dados['situacao'] = 'aguardando_comprovante';
                }
                if ($codigo === 'expenses') {
                    $dados['situacao'] = 'em_conferencia';
                }
                if ($codigo === 'maintenance') {
                    $dados['situacao'] = 'planejada';
                    $dados['tipo'] = 'preventiva';
                }
                if ($codigo === 'tickets') {
                    $dados['situacao'] = 'aberto';
                    $dados['versao'] = 1;
                }
                if ($codigo === 'fuel') {
                    continue;
                } // Compartilha despesas; detalhes físicos inseridos abaixo.
                if ($codigo === 'technical-routes') {
                    continue;
                } // Catálogo real do SQL.
                DB::table($tela['table'])->insert($dados);
            }
        }
        for ($id = 1; $id <= 3; $id++) {
            DB::table('veiculos')->insert(['id' => $id, 'unidade_id' => $id === 3 ? 2 : 1, 'placa' => 'ABC1D2'.$id, 'nome' => 'Veículo de teste '.$id, 'criado_por' => $id, 'quilometragem_atual' => 25000]);
            DB::table('multas')->insert(['id' => $id, 'protocolo' => 'QA-MUL-'.$id, 'veiculo_id' => $id, 'unidade_id' => $id === 3 ? 2 : 1, 'ocorrido_em' => '2026-10-05 12:00:00', 'precisao_ocorrencia' => 'instante', 'valor' => 12345.67, 'descricao' => 'Autuação de teste isolado', 'situacao' => 'sem_responsavel', 'criado_por' => $id, 'versao' => 1]);
            DB::table('despesas')->insert(['id' => $id, 'unidade_id' => $id === 3 ? 2 : 1, 'veiculo_id' => $id, 'protocolo' => 'QA-EXP-'.$id, 'criado_por' => $id, 'valor' => 12345.67, 'data_despesa' => '2026-10-05', 'situacao' => 'em_conferencia']);
            DB::table('abastecimentos')->insert(['despesa_id' => $id, 'veiculo_id' => $id, 'combustivel' => 'gasolina', 'quantidade' => 20, 'unidade_medida' => 'litro', 'preco_unitario' => 6, 'quilometragem' => 25000]);
            DB::table('reservas')->insert(['id' => $id, 'veiculo_id' => $id, 'inicio' => '2026-10-05 12:00:00', 'fim' => '2026-10-05 18:00:00', 'situacao' => 'ativa', 'tipo' => 'viagem']);
        }
        DB::table('usuario_perfis')->insert(['id' => 20, 'usuario_id' => 2, 'perfil_id' => 1, 'unidade_id' => 1]);
        DB::table('arquivos')->insert(['id' => 2, 'enviado_por' => 2, 'nome_original' => 'comprovante-isolado.pdf', 'situacao' => 'disponivel']);
        DB::table('multa_comprovantes')->insert(['id' => 2, 'multa_id' => 2, 'numero' => 1, 'arquivo_id' => 2, 'enviado_por_vinculo_id' => 20, 'valor_declarado' => 12345.67, 'enviado_em' => '2026-10-05 12:00:00']);
    }

    public static function profile(int $perfil): stdClass
    {
        DB::table('vw_permissoes_efetivas')->delete();
        $permissoes = DB::table('perfil_permissoes as pp')->join('permissoes as p', 'p.id', '=', 'pp.permissao_id')->where('pp.perfil_id', $perfil)->get(['p.modulo_codigo', 'p.acao_codigo', 'p.alcance', 'pp.delegavel']);
        foreach ($permissoes as $permissao) {
            self::grant($permissao->modulo_codigo, $permissao->acao_codigo, match ($permissao->alcance) {
                'proprios' => 1,'unidade' => 2,default => 3
            }, (bool) $permissao->delegavel);
        }
        $vinculo = (object) ['vinculo_id' => 10, 'usuario_id' => 1, 'unidade_id' => 1, 'perfil_id' => $perfil, 'perfil_nome' => DB::table('perfis')->where('id', $perfil)->value('nome'), 'perfil_codigo' => DB::table('perfis')->where('id', $perfil)->value('codigo'), 'unidade_nome' => 'Sede administrativa'];
        Auth::guard()->setUser(User::findOrFail(1));
        app(AccessContext::class)->load($vinculo);
        $servico = Mockery::mock(FleetSession::class);
        $servico->shouldReceive('validate')->andReturn($vinculo);
        app()->instance(FleetSession::class, $servico);

        return $vinculo;
    }

    public static function grant(string $modulo, string $acao, int $nivel, bool $delegavel = false): void
    {
        DB::table('vw_permissoes_efetivas')->insert(['vinculo_id' => 10, 'usuario_id' => 1, 'unidade_id' => 1, 'modulo_codigo' => $modulo, 'acao_codigo' => $acao, 'nivel_alcance' => $nivel, 'delegavel' => $delegavel]);
    }
}
