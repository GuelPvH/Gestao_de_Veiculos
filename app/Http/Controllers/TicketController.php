<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketActionRequest;
use App\Http\Requests\TicketOpenRequest;
use App\Services\Authorization\AccessContext;
use App\Services\Read\FleetReadRepository;
use App\Services\Tickets\TicketService;
use App\Support\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function index(Request $requisicao, FleetReadRepository $leituras, TicketService $chamados): View
    {
        $chamados->authorizeList();
        /** @var array<string, mixed> $filtros */
        $filtros = $requisicao->validate([
            'q' => 'nullable|string|max:150',
            'situacao' => 'nullable|string|max:60',
            'de' => 'nullable|date_format:Y-m-d',
            'ate' => 'nullable|date_format:Y-m-d|after_or_equal:de',
            'ordem' => 'nullable|in:recentes,antigos',
            'page' => 'nullable|integer|min:1',
        ]);
        $paginacao = $leituras->page('tickets', $filtros);

        return view('tickets.index', [
            'codigo' => 'tickets', 'tela' => $leituras->definition('tickets'), 'filtros' => $filtros,
            'paginacao' => $paginacao, 'registros' => $leituras->rows('tickets', $paginacao->getCollection()),
            'podeCriar' => $chamados->canCreate(),
        ]);
    }

    public function show(int $registro, TicketService $chamados, FleetReadRepository $leituras, AccessContext $acesso): View
    {
        $chamado = $chamados->ticket($registro);
        $mensagensQuery = DB::table('chamado_mensagens as m')->join('usuario_perfis as v', 'v.id', '=', 'm.autor_vinculo_id')
            ->join('usuarios as u', 'u.id', '=', 'v.usuario_id')->where('m.chamado_id', $registro);
        if (! $acesso->can('chamados', 'atender', Value::integer($chamado->__owner), Value::integer($chamado->__unit))) {
            $mensagensQuery->where('m.interna', 0);
        }
        $mensagensSub = DB::query()->fromSub(
            $mensagensQuery->orderBy('m.id')->limit(100)
                ->selectRaw("'message' as kind, m.id as ord, u.nome as a, m.mensagem as b, m.interna as c, m.criado_em as d"),
            'msg',
        );
        $anexosSub = DB::query()->fromSub(
            DB::table('anexos as a')->join('arquivos as f', 'f.id', '=', 'a.arquivo_id')
                ->where('a.chamado_id', $registro)->where('f.situacao', 'disponivel')->orderByDesc('a.id')->limit(40)
                ->selectRaw("'attachment' as kind, a.id as ord, f.nome_original as a, f.tamanho_bytes as b, NULL as c, NULL as d"),
            'att',
        );
        $eventosSub = DB::query()->fromSub(
            DB::table('chamado_eventos')->where('chamado_id', $registro)->orderByDesc('id')->limit(40)
                ->selectRaw("'event' as kind, id as ord, tipo as a, criado_em as b, motivo as c, NULL as d"),
            'evt',
        );
        $linhas = $mensagensSub->unionAll($anexosSub)->unionAll($eventosSub)->get();
        $mensagens = $linhas->where('kind', 'message')->sortBy('ord')
            ->map(fn (\stdClass $row): \stdClass => (object) [
                'autor' => $row->a, 'mensagem' => $row->b, 'interna' => $row->c, 'criado_em' => $row->d,
            ])->values();
        $anexos = $linhas->where('kind', 'attachment')->sortByDesc('ord')
            ->map(fn (\stdClass $row): \stdClass => (object) [
                'id' => $row->ord, 'nome_original' => $row->a, 'tamanho_bytes' => $row->b,
            ])->values();
        $eventos = $leituras->mapHistoryRows('tickets', $linhas->where('kind', 'event')->sortByDesc('ord')->values());

        return view('tickets.show', [
            'chamado' => $chamado,
            'valores' => $leituras->rows('tickets', collect([$chamado]))->sole()['valores'],
            'tela' => $leituras->definition('tickets'),
            'mensagens' => $mensagens,
            'anexos' => $anexos,
            'eventos' => $eventos,
            'operacoes' => $chamados->actions($chamado),
            'leituras' => $leituras,
        ]);
    }

    public function create(Request $requisicao, TicketService $chamados): View
    {
        abort_unless($chamados->canCreate(), 403);

        return view('tickets.form', [
            'acao' => 'create', 'chamado' => null, 'titulo' => 'Novo chamado',
            'categorias' => DB::table('categorias_chamado')->where('ativa', 1)->pluck('nome', 'id')->all(),
            'atendentes' => [],
            'paginaContexto' => Value::string($requisicao->query('pagina_contexto', '')),
        ]);
    }

    public function store(TicketOpenRequest $requisicao, TicketService $chamados): RedirectResponse
    {
        $id = $chamados->open($requisicao->validated());

        return redirect()->route('tickets.show', $id)->with('status', 'Chamado aberto. Você já pode anexar um documento.');
    }

    public function operation(int $registro, string $acao, TicketService $chamados): View|RedirectResponse
    {
        $chamado = $chamados->ticket($registro);
        $rotulos = $chamados->actions($chamado);
        abort_unless(isset($rotulos[$acao]), 403);

        if ($acao === 'attach') {
            return redirect()->route('tickets.show', ['registro' => $registro, 'anexar' => 1]);
        }

        return view('tickets.form', [
            'acao' => $acao, 'chamado' => $chamado, 'titulo' => $rotulos[$acao],
            'categorias' => [], 'atendentes' => $acao === 'assign' ? $chamados->attendants(Value::integer($chamado->__unit)) : [],
        ]);
    }

    public function perform(TicketActionRequest $requisicao, int $registro, string $acao, TicketService $chamados): RedirectResponse
    {
        $dados = $requisicao->validated();
        if ($acao === 'respond' || $acao === 'note') {
            $chamados->reply($registro, Value::string($dados['mensagem']), $acao === 'note');
            $status = $acao === 'note' ? 'Nota interna registrada.' : 'Resposta registrada.';
        } elseif ($acao === 'assign') {
            if (! $chamados->assign($registro, Value::integer($dados['versao']), Value::string($dados['situacao']), Value::integer($dados['responsavel']), Value::string($dados['motivo']))) {
                return $this->conflict($registro);
            }
            $status = 'Atendimento atualizado.';
        } elseif ($acao === 'resolve') {
            if (! $chamados->resolve($registro, Value::integer($dados['versao']), Value::string($dados['motivo']))) {
                return $this->conflict($registro);
            }
            $status = 'Chamado resolvido.';
        } else {
            $chamados->attach($registro, $requisicao->file('arquivo'));
            $status = 'Anexo privado acrescentado.';
        }

        return redirect()->route('tickets.show', $registro)->with('status', $status);
    }

    public function download(int $registro, int $anexo, TicketService $chamados): StreamedResponse
    {
        $arquivo = $chamados->attachment($registro, $anexo);
        abort_unless(Storage::disk('local')->exists(Value::string($arquivo->chave_armazenamento)), 404);

        return Storage::disk('local')->download(Value::string($arquivo->chave_armazenamento), Value::nullableString($arquivo->nome_original), ['Content-Type' => 'application/octet-stream']);
    }

    private function conflict(int $registro): RedirectResponse
    {
        return redirect()->route('tickets.show', $registro)->withErrors(['chamado' => 'O chamado foi alterado. Atualize a página antes de tentar novamente.']);
    }
}
