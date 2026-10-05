<?php

namespace App\Services\Tickets;

use App\Services\Auth\ProcedureRunner;
use App\Services\Authorization\AccessContext;
use App\Services\Files\PrivateFileService;
use App\Services\Read\FleetReadRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDOException;
use stdClass;
use UnexpectedValueException;

class TicketService
{
    public function __construct(
        private AccessContext $acesso,
        private FleetReadRepository $leituras,
        private ProcedureRunner $procedimentos,
        private PrivateFileService $arquivos,
    ) {}

    public function canCreate(): bool
    {
        return $this->hasIdentity() && $this->acesso->can('chamados', 'criar', (int) Auth::id(), (int) $this->acesso->link()->unidade_id);
    }

    public function authorizeList(): void
    {
        abort_unless($this->hasIdentity() && $this->acesso->level('chamados', 'consultar') > 0, 403);
    }

    public function ticket(int $id): stdClass
    {
        abort_unless($this->hasIdentity(), 403);
        $chamado = $this->leituras->record('tickets', $id);
        $versao = DB::table('chamados')->where('id', $id)->value('versao');
        abort_unless($versao !== null, 404);
        $chamado->versao = (int) $versao;

        return $chamado;
    }

    public function actions(stdClass $chamado): array
    {
        $rotulos = ['respond' => 'Responder chamado', 'note' => 'Adicionar nota interna', 'assign' => 'Atribuir atendimento', 'resolve' => 'Resolver chamado', 'attach' => 'Anexar documento'];

        return array_filter($rotulos, fn (string $rotulo, string $acao): bool => $this->canAction($chamado, $acao), ARRAY_FILTER_USE_BOTH);
    }

    public function canAction(stdClass $chamado, string $acao): bool
    {
        if (! $this->hasIdentity() || $chamado->situacao === 'resolvido') {
            return false;
        }
        $dono = (int) $chamado->__owner;
        $unidade = (int) $chamado->__unit;
        $responder = $this->acesso->can('chamados', 'responder', $dono, $unidade);
        $atender = $this->acesso->can('chamados', 'atender', $dono, $unidade);

        return match ($acao) {
            'respond', 'attach' => $responder,
            'note' => $responder && $atender,
            'assign' => $atender,
            'resolve' => $this->acesso->can('chamados', 'resolver', $dono, $unidade),
            default => false,
        };
    }

    public function open(array $dados): int
    {
        abort_unless($this->canCreate(), 403);
        $retorno = $this->procedimentos->call('sp_abrir_chamado', [
            (int) $this->acesso->link()->vinculo_id,
            (int) $dados['categoria'],
            $dados['assunto'],
            $dados['descricao'],
            $dados['pagina_contexto'] ?? null,
        ]);
        $id = $retorno[0]['chamado_id'] ?? null;
        if (! is_numeric($id) || (int) $id < 1) {
            throw new UnexpectedValueException('A procedure de abertura não retornou o chamado criado.');
        }

        return (int) $id;
    }

    public function reply(int $id, string $mensagem, bool $interna): void
    {
        $chamado = $this->ticket($id);
        abort_unless($this->canAction($chamado, $interna ? 'note' : 'respond'), 403);
        $this->procedimentos->call('sp_responder_chamado', [(int) $this->acesso->link()->vinculo_id, $id, $mensagem, (int) $interna]);
    }

    public function assign(int $id, int $versao, string $situacao, int $atendente, string $motivo): bool
    {
        $chamado = $this->ticket($id);
        abort_unless($this->canAction($chamado, 'assign'), 403);
        if ($chamado->versao !== $versao) {
            return false;
        }
        if (! in_array($situacao, ['em_atendimento', 'aguardando_solicitante'], true) || ! $this->attendantEligible($atendente, (int) $chamado->__unit)) {
            throw ValidationException::withMessages(['responsavel' => 'Selecione um atendente com vínculo vigente para esta fila.']);
        }

        return $this->attend($id, $versao, $situacao, $atendente, $motivo);
    }

    public function resolve(int $id, int $versao, string $motivo): bool
    {
        $chamado = $this->ticket($id);
        abort_unless($this->canAction($chamado, 'resolve'), 403);
        if ($chamado->versao !== $versao) {
            return false;
        }

        return $this->attend($id, $versao, 'resolvido', null, $motivo);
    }

    public function attach(int $id, UploadedFile $arquivo): void
    {
        $chamado = $this->ticket($id);
        abort_unless($this->canAction($chamado, 'attach'), 403);
        $this->arquivos->store($arquivo, function (int $arquivoId) use ($id): void {
            $atual = DB::table('chamados')->where('id', $id)->lockForUpdate()->first(['solicitante_id', 'unidade_id', 'situacao']);
            abort_unless($atual !== null && $atual->situacao !== 'resolvido', 409);
            $this->acesso->load($this->acesso->link());
            abort_unless($this->acesso->can('chamados', 'responder', (int) $atual->solicitante_id, (int) $atual->unidade_id), 403);
            DB::table('anexos')->insert(['arquivo_id' => $arquivoId, 'chamado_id' => $id]);
            DB::table('auditoria')->insert([
                'ator_usuario_id' => (int) Auth::id(),
                'ator_vinculo_id' => (int) $this->acesso->link()->vinculo_id,
                'evento' => 'anexo_chamado',
                'entidade' => 'chamados',
                'entidade_id' => $id,
                'descricao' => 'Anexo privado acrescentado ao chamado.',
            ]);
        });
    }

    public function attachment(int $chamadoId, int $anexoId): stdClass
    {
        $this->ticket($chamadoId);
        $anexo = DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
            ->where('a.id', $anexoId)->where('a.chamado_id', $chamadoId)->where('f.situacao', 'disponivel')
            ->first(['f.chave_armazenamento', 'f.nome_original']);
        abort_unless($anexo !== null && str_starts_with($anexo->chave_armazenamento, 'anexos/'), 404);

        return $anexo;
    }

    public function attendants(int $unidade): array
    {
        return DB::table('vw_permissoes_efetivas as p')->join('usuarios as u', 'u.id', '=', 'p.usuario_id')
            ->where('p.modulo_codigo', 'chamados')->where('p.acao_codigo', 'atender')
            ->where(function ($consulta) use ($unidade): void {
                $consulta->where('p.alcance', 'orgao')->orWhere(function ($local) use ($unidade): void {
                    $local->where('p.alcance', 'unidade')->where('p.unidade_id', $unidade);
                });
            })->where('u.ativo', 1)->distinct()->orderBy('u.nome')->pluck('u.nome', 'u.id')->all();
    }

    private function hasIdentity(): bool
    {
        $vinculo = $this->acesso->link();
        $usuario = Auth::id();

        return $vinculo !== null && $usuario !== null && (int) $vinculo->usuario_id === (int) $usuario;
    }

    private function attendantEligible(int $usuario, int $unidade): bool
    {
        return DB::table('vw_permissoes_efetivas')->where('usuario_id', $usuario)->where('modulo_codigo', 'chamados')->where('acao_codigo', 'atender')
            ->where(function ($consulta) use ($unidade): void {
                $consulta->where('alcance', 'orgao')->orWhere(function ($local) use ($unidade): void {
                    $local->where('alcance', 'unidade')->where('unidade_id', $unidade);
                });
            })->exists();
    }

    private function attend(int $id, int $versao, string $situacao, ?int $atendente, string $motivo): bool
    {
        try {
            $this->procedimentos->call('sp_atender_chamado', [(int) $this->acesso->link()->vinculo_id, $id, $versao, $situacao, $atendente, $motivo]);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[0] ?? (string) $erro->getCode()) === '45000'
                && str_contains($erro->getMessage(), 'Chamado, versão ou situação inválida.')) {
                return false;
            }
            throw $erro;
        }

        return true;
    }
}
