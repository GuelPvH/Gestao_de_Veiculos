<?php

namespace App\Services\Fines;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Files\PrivateFileService;
use App\Services\Read\FleetReadRepository;
use App\Services\Read\OperationCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use stdClass;
use Throwable;

class FineWorkflow
{
    private const ACTIONS = ['edit', 'assign', 'proof', 'verify', 'correct', 'settle', 'dispute', 'cancel'];

    public function __construct(
        private AccessContext $acesso,
        private FleetReadRepository $leituras,
        private OperationCatalog $operacoes,
        private ProcedureRunner $procedimentos,
        private PrivateFileService $arquivos,
    ) {}

    public function vehicleOptions(): array
    {
        $vinculo = $this->identity();
        $nivel = $this->acesso->level('multas', 'criar');
        if ($nivel === 0) {
            return [];
        }
        $consulta = DB::table('veiculos')->where('situacao_cadastro', 'ativo');
        if ($nivel < 3) {
            $consulta->where('unidade_id', (int) $vinculo->unidade_id);
        }

        return $consulta->orderBy('placa')->limit(300)->get(['id', 'placa', 'nome'])->mapWithKeys(fn (stdClass $veiculo) => [$veiculo->id => $veiculo->placa.' · '.$veiculo->nome])->all();
    }

    public function tripOptions(int $multaId): array
    {
        $multa = $this->fine($multaId);
        $inicio = $multa->ocorrido_em;
        $fim = $multa->precisao_ocorrencia === 'dia' ? $multa->fim_dia_ocorrencia_utc : $inicio;
        $consulta = DB::table('viagens as v')->join('usuarios as u', 'u.id', '=', 'v.motorista_id')
            ->where('v.veiculo_id', $multa->veiculo_id)->whereIn('v.situacao', ['em_andamento', 'concluida'])
            ->whereNotNull('v.saida_real')->where('v.saida_real', $multa->precisao_ocorrencia === 'dia' ? '<' : '<=', $fim)
            ->where(function ($query) use ($inicio): void {
                $query->whereNull('v.retorno_real')->orWhere('v.retorno_real', '>=', $inicio);
            });

        return $consulta->orderByDesc('v.saida_real')->limit(200)->get(['v.id', 'v.protocolo', 'u.nome'])->mapWithKeys(fn (stdClass $viagem) => [$viagem->id => $viagem->protocolo.' · '.$viagem->nome])->all();
    }

    public function responsibleOptions(int $multaId): array
    {
        $multa = $this->fine($multaId);

        return DB::table('usuarios')->where('ativo', 1)->where('unidade_id', $multa->unidade_id)
            ->orderBy('nome')->limit(300)->pluck('nome', 'id')->all();
    }

    public function create(array $dados): int
    {
        $vinculo = $this->identity();
        $veiculo = DB::table('veiculos')->where('id', $dados['veiculo_id'])->first(['id', 'unidade_id', 'situacao_cadastro']);
        abort_unless($veiculo !== null && $veiculo->situacao_cadastro === 'ativo', 422);
        $usuario = (int) $vinculo->usuario_id;
        abort_unless($this->acesso->can('multas', 'criar', $usuario, (int) $veiculo->unidade_id), 403);
        if ($this->acesso->level('multas', 'criar') < 3) {
            abort_unless((int) $veiculo->unidade_id === (int) $vinculo->unidade_id, 403);
        }
        [$ocorrido, $fim] = $this->occurrenceBounds($dados['ocorrido_em'], $dados['precisao_ocorrencia']);
        $this->uniqueAuto($dados['orgao_autuador'] ?? null, $dados['numero_auto'] ?? null);

        try {
            return DB::transaction(function () use ($vinculo, $veiculo, $dados, $ocorrido, $fim, $usuario): int {
                $this->procedimentos->call('sp_exigir_permissao', [(int) $vinculo->vinculo_id, 'multas', 'criar', $usuario, (int) $veiculo->unidade_id]);
                $id = (int) DB::table('multas')->insertGetId([
                    'protocolo' => 'MUL-'.strtoupper(str_replace('-', '', (string) Str::uuid())),
                    'veiculo_id' => (int) $veiculo->id,
                    'unidade_id' => (int) $veiculo->unidade_id,
                    'numero_auto' => $this->optional($dados['numero_auto'] ?? null),
                    'orgao_autuador' => $this->optional($dados['orgao_autuador'] ?? null),
                    'ocorrido_em' => $ocorrido,
                    'precisao_ocorrencia' => $dados['precisao_ocorrencia'],
                    'fim_dia_ocorrencia_utc' => $fim,
                    'data_vencimento' => $dados['data_vencimento'] ?? null,
                    'valor' => $dados['valor'],
                    'descricao' => trim($dados['descricao']),
                    'situacao' => 'sem_responsavel',
                    'criado_por' => $usuario,
                ]);
                DB::table('multa_eventos')->insert([
                    'multa_id' => $id, 'ator_vinculo_id' => (int) $vinculo->vinculo_id,
                    'tipo' => 'criada', 'situacao_nova' => 'sem_responsavel',
                    'motivo' => 'Autuação registrada; responsabilidade pendente de apuração.',
                ]);
                $this->procedimentos->call('sp_auditar', [(int) $vinculo->vinculo_id, 'multa_criada', 'multas', $id, 'Autuação registrada sem responsável automático.']);

                return $id;
            }, 3);
        } catch (PDOException $erro) {
            $this->procedureConflict($erro);
        }
    }

    public function perform(int $id, string $acao, array $dados, ?UploadedFile $comprovante): void
    {
        abort_unless(in_array($acao, self::ACTIONS, true), 404);
        $this->identity();
        $visivel = $this->leituras->record('fines', $id);
        abort_unless(isset($this->operacoes->allowed('fines', $visivel)[$acao]), 403);
        try {
            if ($acao === 'edit') {
                $this->edit($id, $dados);

                return;
            }
            $multa = $this->fine($id);
            $this->checkVersion($multa, (int) $dados['versao']);
            $vinculo = (int) $this->acesso->link()->vinculo_id;
            match ($acao) {
                'assign' => $this->assign($multa, $dados, $vinculo),
                'proof' => $this->proof($multa, $dados, $comprovante, $vinculo),
                'verify', 'correct', 'settle' => $this->verify($multa, $acao, $dados, $vinculo),
                'dispute', 'cancel' => $this->procedimentos->call('sp_alterar_situacao_multa', [
                    $vinculo, $id, (int) $dados['versao'], $acao === 'dispute' ? 'contestada' : 'cancelada', trim($dados['justificativa']),
                ]),
            };
        } catch (PDOException $erro) {
            $this->procedureConflict($erro);
        }
    }

    public function receipt(int $multaId, int $comprovanteId): stdClass
    {
        $multa = $this->leituras->record('fines', $multaId);
        abort_unless($this->acesso->can('multas', 'ver_valores', $multa->__owner !== null ? (int) $multa->__owner : null, (int) $multa->__unit), 403);
        $arquivo = DB::table('multa_comprovantes as c')->join('arquivos as a', 'a.id', '=', 'c.arquivo_id')
            ->where('c.id', $comprovanteId)->where('c.multa_id', $multaId)->where('a.situacao', 'disponivel')
            ->first(['a.chave_armazenamento', 'a.nome_original']);
        abort_unless($arquivo !== null && is_string($arquivo->chave_armazenamento) && str_starts_with($arquivo->chave_armazenamento, 'anexos/'), 404);

        return $arquivo;
    }

    private function edit(int $id, array $dados): void
    {
        DB::transaction(function () use ($id, $dados): void {
            $multa = $this->fine($id, true);
            $this->checkVersion($multa, (int) $dados['versao']);
            if ($multa->situacao !== 'sem_responsavel' || DB::table('multa_comprovantes')->where('multa_id', $id)->exists()) {
                $this->invalidState();
            }
            abort_unless($this->acesso->can('multas', 'editar', $multa->responsavel_id !== null ? (int) $multa->responsavel_id : null, (int) $multa->unidade_id), 403);
            $vinculo = (int) $this->acesso->link()->vinculo_id;
            $this->procedimentos->call('sp_exigir_permissao', [$vinculo, 'multas', 'editar', $multa->responsavel_id, (int) $multa->unidade_id]);
            $this->uniqueAuto($dados['orgao_autuador'] ?? null, $dados['numero_auto'] ?? null, $id);
            $afetados = DB::table('multas')->where('id', $id)->where('versao', $dados['versao'])->update([
                'numero_auto' => $this->optional($dados['numero_auto'] ?? null),
                'orgao_autuador' => $this->optional($dados['orgao_autuador'] ?? null),
                'data_vencimento' => $dados['data_vencimento'] ?? null,
                'valor' => $dados['valor'],
                'descricao' => trim($dados['descricao']),
                'versao' => DB::raw('versao + 1'),
            ]);
            if ($afetados !== 1) {
                $this->conflict();
            }
            DB::table('multa_eventos')->insert([
                'multa_id' => $id, 'ator_vinculo_id' => $vinculo, 'tipo' => 'editada',
                'situacao_anterior' => $multa->situacao, 'situacao_nova' => $multa->situacao,
                'motivo' => trim($dados['justificativa']),
            ]);
            $this->procedimentos->call('sp_auditar', [$vinculo, 'multa_editada', 'multas', $id, mb_substr(trim($dados['justificativa']), 0, 1000)]);
        }, 3);
    }

    private function assign(stdClass $multa, array $dados, int $vinculo): void
    {
        if (! in_array($multa->situacao, ['sem_responsavel', 'aguardando_comprovante', 'contestada'], true)
            || DB::table('multa_comprovantes')->where('multa_id', $multa->id)->exists()) {
            $this->invalidState();
        }
        $viagem = DB::table('viagens')->where('id', $dados['viagem_id'])->where('veiculo_id', $multa->veiculo_id)
            ->whereIn('situacao', ['em_andamento', 'concluida'])->first(['motorista_id', 'saida_real', 'retorno_real']);
        if ($viagem === null || $viagem->saida_real === null || ! $this->overlaps($multa, $viagem)) {
            throw ValidationException::withMessages(['viagem_id' => 'Selecione uma viagem efetiva deste veículo que coincida com a autuação.']);
        }
        $responsavel = DB::table('usuarios')->where('id', $dados['responsavel_id'])->where('ativo', 1)->where('unidade_id', $multa->unidade_id)->exists();
        if (! $responsavel) {
            throw ValidationException::withMessages(['responsavel_id' => 'Selecione um responsável ativo da unidade.']);
        }
        $this->procedimentos->call('sp_atribuir_responsavel_multa', [
            $vinculo, (int) $multa->id, (int) $dados['versao'], (int) $dados['viagem_id'],
            (int) $dados['responsavel_id'], trim($dados['justificativa']),
        ]);
    }

    private function proof(stdClass $multa, array $dados, ?UploadedFile $comprovante, int $vinculo): void
    {
        if ($multa->situacao !== 'aguardando_comprovante' || $multa->responsabilidade_atual_id === null) {
            $this->invalidState();
        }
        if ($comprovante === null) {
            throw ValidationException::withMessages(['comprovante' => 'Selecione o comprovante.']);
        }
        $pagoEm = $this->pastUtc($dados['pagamento_em'], 'pagamento_em');
        $arquivoId = $this->arquivos->store($comprovante);
        try {
            $this->procedimentos->call('sp_enviar_comprovante_multa', [
                $vinculo, (int) $multa->id, (int) $dados['versao'], $arquivoId,
                $dados['valor_declarado'], $pagoEm, $this->optional($dados['observacao'] ?? null),
            ]);
        } catch (Throwable $erro) {
            $this->arquivos->discardUnlinked($arquivoId);
            throw $erro;
        }
    }

    private function verify(stdClass $multa, string $acao, array $dados, int $vinculo): void
    {
        if ($multa->situacao !== 'em_conferencia') {
            $this->invalidState();
        }
        $recibo = DB::table('multa_comprovantes as c')->join('usuario_perfis as p', 'p.id', '=', 'c.enviado_por_vinculo_id')
            ->join('multa_responsabilidades as r', 'r.id', '=', 'c.responsabilidade_id')
            ->where('c.multa_id', $multa->id)->orderByDesc('c.numero')
            ->first(['c.id', 'p.usuario_id as enviado_por', 'r.responsavel_id']);
        if ($recibo === null || (int) $recibo->enviado_por === (int) Auth::id() || (int) $recibo->responsavel_id === (int) Auth::id()) {
            abort(403);
        }
        $resultado = $acao === 'correct' ? 'correcao_solicitada' : ($acao === 'settle' ? 'aceito' : $dados['resultado']);
        $motivo = $this->optional($dados['motivo'] ?? null);
        $valor = $resultado === 'aceito' ? ($dados['valor_confirmado'] ?? null) : null;
        $pagoEm = $resultado === 'aceito' ? $this->pastUtc($dados['pagamento_confirmado_em'], 'pagamento_confirmado_em') : null;
        if ($resultado === 'correcao_solicitada' && $motivo === null) {
            throw ValidationException::withMessages(['motivo' => 'Informe a correção necessária.']);
        }
        if ($resultado === 'aceito' && (round((float) $valor * 100) !== round((float) $multa->valor * 100)) && $motivo === null) {
            throw ValidationException::withMessages(['motivo' => 'Explique a diferença entre o valor pago e o valor da multa.']);
        }
        $this->procedimentos->call('sp_conferir_comprovante_multa', [
            $vinculo, (int) $recibo->id, $resultado, $motivo, $valor, $pagoEm,
        ]);
    }

    private function fine(int $id, bool $bloquear = false): stdClass
    {
        $consulta = DB::table('multas as m')->leftJoin('multa_responsabilidades as r', 'r.id', '=', 'm.responsabilidade_atual_id')->where('m.id', $id);
        if ($bloquear) {
            $consulta->lockForUpdate();
        }
        $multa = $consulta->first(['m.id', 'm.veiculo_id', 'm.unidade_id', 'm.situacao', 'm.versao', 'm.valor', 'm.ocorrido_em', 'm.precisao_ocorrencia', 'm.fim_dia_ocorrencia_utc', 'm.responsabilidade_atual_id', 'r.responsavel_id']);
        abort_unless($multa !== null, 404);

        return $multa;
    }

    private function identity(): stdClass
    {
        $vinculo = $this->acesso->link();
        abort_unless($vinculo !== null && Auth::id() !== null && (int) $vinculo->usuario_id === (int) Auth::id(), 403);

        return $vinculo;
    }

    private function occurrenceBounds(string $valor, string $precisao): array
    {
        $instante = CarbonImmutable::parse($valor, config('fleet.timezone'));
        if ($precisao === 'dia') {
            $inicio = $instante->startOfDay()->utc();
            $fim = $instante->startOfDay()->addDay()->utc();
        } else {
            $inicio = $instante->utc();
            $fim = null;
        }
        if ($inicio->greaterThan(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages(['ocorrido_em' => 'A ocorrência não pode ser futura.']);
        }

        return [$inicio->format('Y-m-d H:i:s.u'), $fim?->format('Y-m-d H:i:s.u')];
    }

    private function pastUtc(string $valor, string $campo): string
    {
        $instante = CarbonImmutable::parse($valor, config('fleet.timezone'))->utc();
        if ($instante->greaterThan(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages([$campo => 'A data e hora não podem ser futuras.']);
        }

        return $instante->format('Y-m-d H:i:s.u');
    }

    private function overlaps(stdClass $multa, stdClass $viagem): bool
    {
        $inicio = CarbonImmutable::parse($viagem->saida_real, 'UTC');
        $fim = $viagem->retorno_real ? CarbonImmutable::parse($viagem->retorno_real, 'UTC') : CarbonImmutable::now('UTC');
        $ocorrido = CarbonImmutable::parse($multa->ocorrido_em, 'UTC');
        if ($multa->precisao_ocorrencia === 'dia') {
            return $ocorrido->lessThan($fim) && CarbonImmutable::parse($multa->fim_dia_ocorrencia_utc, 'UTC')->greaterThan($inicio);
        }

        return $ocorrido->betweenIncluded($inicio, $fim);
    }

    private function uniqueAuto(?string $orgao, ?string $numero, ?int $except = null): void
    {
        $orgao = $this->optional($orgao);
        $numero = $this->optional($numero);
        if ($orgao === null || $numero === null) {
            return;
        }
        $consulta = DB::table('multas')->where('orgao_autuador', $orgao)->where('numero_auto', $numero);
        if ($except !== null) {
            $consulta->where('id', '<>', $except);
        }
        if ($consulta->exists()) {
            throw ValidationException::withMessages(['numero_auto' => 'Este auto já foi registrado para o órgão informado.']);
        }
    }

    private function optional(?string $valor): ?string
    {
        $limpo = trim((string) $valor);

        return $limpo === '' ? null : $limpo;
    }

    private function checkVersion(stdClass $multa, int $versao): void
    {
        if ((int) $multa->versao !== $versao) {
            $this->conflict();
        }
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['versao' => 'Esta multa mudou. Atualize a página antes de continuar.']);
    }

    private function invalidState(): never
    {
        throw ValidationException::withMessages(['operacao' => 'A situação da multa mudou. Atualize a página antes de continuar.']);
    }

    private function procedureConflict(PDOException $erro): never
    {
        if (($erro->errorInfo[0] ?? (string) $erro->getCode()) === '23000' && str_contains($erro->getMessage(), 'uq_multa_auto')) {
            throw ValidationException::withMessages(['numero_auto' => 'Este auto já foi registrado para o órgão informado.']);
        }
        if (($erro->errorInfo[0] ?? (string) $erro->getCode()) === '45000') {
            throw ValidationException::withMessages(['operacao' => 'A multa, permissão ou comprovante mudou. Atualize a página e confira os dados.']);
        }

        throw $erro;
    }
}
