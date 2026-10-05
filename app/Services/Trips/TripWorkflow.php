<?php

namespace App\Services\Trips;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDOException;
use stdClass;

class TripWorkflow
{
    private const ACTIONS = [
        'departure' => 'registrar_saida',
        'return' => 'registrar_retorno',
        'occurrence' => 'registrar_ocorrencia',
        'cancel' => 'cancelar',
    ];

    public function __construct(private AccessContext $acesso, private ProcedureRunner $procedimentos) {}

    public function perform(int $registro, string $acao, array $validado, array $entrada): void
    {
        abort_unless(isset(self::ACTIONS[$acao]), 404);
        if ($acao === 'occurrence') {
            $this->occurrence($registro, $validado);

            return;
        }

        $viagem = $this->trip($registro);
        $this->authorize($viagem, $acao);
        $this->checkVersion($viagem, (int) $validado['versao']);
        $esperado = $acao === 'return' ? 'em_andamento' : 'programada';
        $this->checkState($viagem, $esperado);
        $vinculo = (int) $this->acesso->link()->vinculo_id;
        try {
            if ($acao === 'cancel') {
                if ((int) $viagem->solicitacao_versao !== (int) $validado['solicitacao_versao']) {
                    $this->conflict();
                }
                // A rotina canônica cancela a solicitação, viagem programada e reserva juntas.
                $this->procedimentos->call('sp_cancelar_solicitacao', [$vinculo, (int) $viagem->solicitacao_id, (int) $validado['solicitacao_versao'], trim($validado['justificativa'])]);

                return;
            }

            $instante = $this->utc($validado['data_registro']);
            $this->checkInstant($instante);
            $checklist = $this->checklist($entrada);
            $this->procedimentos->call($acao === 'departure' ? 'sp_registrar_saida' : 'sp_registrar_retorno', [
                $vinculo,
                $registro,
                (int) $validado['versao'],
                $instante->format('Y-m-d H:i:s.u'),
                (string) $validado['quilometragem'],
                trim($validado['observacoes'] ?? '') ?: null,
                json_encode($checklist, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $erro) {
            $this->procedureConflict($erro);
        }
    }

    private function occurrence(int $registro, array $validado): void
    {
        $instante = $this->utc($validado['ocorrido_em']);
        $this->checkInstant($instante, 'ocorrido_em');
        // A procedure legada não confere estado nem versão. Esta escrita mantém o lock
        // até concluir ocorrência, versão e auditoria na mesma transação.
        DB::transaction(function () use ($registro, $validado, $instante): void {
            $viagem = $this->trip($registro, true);
            $this->authorize($viagem, 'occurrence');
            $this->checkVersion($viagem, (int) $validado['versao']);
            $this->checkState($viagem, 'em_andamento');
            if ($viagem->saida_real === null || $instante->lessThan(CarbonImmutable::parse($viagem->saida_real, 'UTC'))) {
                throw ValidationException::withMessages(['ocorrido_em' => 'A ocorrência deve ser posterior à saída registrada.']);
            }
            $descricao = trim($validado['descricao']);
            $identificador = DB::table('viagem_ocorrencias')->insertGetId([
                'viagem_id' => $registro,
                'registrado_por' => (int) Auth::id(),
                'tipo' => $validado['tipo'],
                'ocorrido_em' => $instante->format('Y-m-d H:i:s.u'),
                'descricao' => $descricao,
            ]);
            $atualizadas = DB::table('viagens')->where('id', $registro)->where('versao', $validado['versao'])->update(['versao' => DB::raw('versao + 1')]);
            if ($atualizadas !== 1) {
                $this->conflict();
            }
            $this->procedimentos->call('sp_auditar', [(int) $this->acesso->link()->vinculo_id, 'ocorrencia_registrada', 'viagem_ocorrencias', $identificador, mb_substr($descricao, 0, 1000)]);
        }, 3);
    }

    private function trip(int $registro, bool $bloquear = false): stdClass
    {
        $consulta = DB::table('viagens as v')
            ->join('solicitacao_revisoes as r', 'r.id', '=', 'v.revisao_id')
            ->join('solicitacoes as s', 's.id', '=', 'r.solicitacao_id')
            ->where('v.id', $registro);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $viagem = $consulta->first(['v.id', 'v.versao', 'v.situacao', 'v.motorista_id', 'v.saida_real', 's.id as solicitacao_id', 's.versao as solicitacao_versao', 's.solicitante_id', 's.unidade_id']);
        abort_unless($viagem !== null, 404);

        return $viagem;
    }

    private function authorize(stdClass $viagem, string $acao): void
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo !== null && Auth::id() !== null && (int) $vinculo->usuario_id === (int) Auth::id(), 403);
        $usuario = (int) $vinculo->usuario_id;
        $dono = $usuario === (int) $viagem->motorista_id ? $usuario : (int) $viagem->solicitante_id;
        $unidade = (int) $viagem->unidade_id;
        abort_unless($this->acesso->can('viagens', 'consultar', $dono, $unidade)
            && $this->acesso->can('viagens', self::ACTIONS[$acao], $dono, $unidade), 403);
        if ($acao === 'cancel') {
            abort_unless($this->acesso->can('solicitacoes', 'cancelar', (int) $viagem->solicitante_id, $unidade), 403);
        }
    }

    private function checkVersion(stdClass $viagem, int $versao): void
    {
        if ((int) $viagem->versao !== $versao) {
            $this->conflict();
        }
    }

    private function checkState(stdClass $viagem, string $esperado): void
    {
        if ($viagem->situacao !== $esperado) {
            throw ValidationException::withMessages(['operacao' => 'O estado desta viagem mudou. Atualize a página.']);
        }
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['versao' => 'Esta viagem mudou. Atualize a página antes de continuar.']);
    }

    private function utc(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor, config('fleet.timezone'))->utc();
    }

    private function checkInstant(CarbonImmutable $instante, string $campo = 'data_registro'): void
    {
        if ($instante->greaterThan(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages([$campo => 'Informe uma data e hora que já ocorreram.']);
        }
    }

    private function checklist(array $entrada): array
    {
        $respostas = [];
        foreach (DB::table('checklist_itens')->where('ativo', 1)->orderBy('ordem')->get(['id', 'obrigatorio']) as $item) {
            $chave = 'item_'.$item->id;
            $resultado = $entrada[$chave] ?? null;
            if (($resultado === null || $resultado === '') && ! $item->obrigatorio) {
                continue;
            }
            if (! in_array($resultado, ['ok', 'problema', 'nao_aplicavel'], true)) {
                throw ValidationException::withMessages([$chave => 'Responda o item da vistoria.']);
            }
            $observacao = trim((string) ($entrada['observacao_'.$item->id] ?? ''));
            if (($resultado === 'problema' && $observacao === '') || mb_strlen($observacao) > 1000) {
                throw ValidationException::withMessages(['observacao_'.$item->id => 'Descreva o problema em até 1000 caracteres.']);
            }
            $respostas[] = ['item_id' => (int) $item->id, 'resultado' => $resultado, 'observacao' => $observacao ?: null];
        }
        if ($respostas === []) {
            throw ValidationException::withMessages(['checklist' => 'A vistoria precisa de ao menos um item ativo respondido.']);
        }

        return $respostas;
    }

    private function procedureConflict(PDOException $erro): never
    {
        if (($erro->errorInfo[0] ?? (string) $erro->getCode()) === '45000') {
            throw ValidationException::withMessages(['operacao' => 'A viagem, reserva ou permissão mudou. Atualize a página e confira os dados.']);
        }

        throw $erro;
    }
}
