<x-layouts.authenticated :titulo="$chamado->protocolo.' · Chamado'" :breadcrumbs="[['label' => 'Chamados', 'href' => route('tickets.index')], ['label' => $chamado->protocolo]]">
    <x-ui.title :titulo="$chamado->protocolo.' · '.$chamado->assunto" subtitulo="Acompanhe a conversa e o histórico deste chamado.">
        <x-slot:acoes><a class="btn btn-outline-secondary" href="{{ route('tickets.index') }}">Voltar à lista</a></x-slot:acoes>
    </x-ui.title>
    <div class="dashboard-grid">
        <div class="stack">
            <x-ui.panel titulo="Informações do chamado">
                <dl class="detail-grid mb-0">
                    @foreach ($tela['columns'] + ($tela['details'] ?? []) as $chave => $campo)
                        <div><dt>{{ $campo[1] }}</dt><dd>@if ($chave === 'situacao') <x-ui.status :valor="$chamado->situacao" /> @else {{ $valores[$chave] ?? '—' }} @endif</dd></div>
                    @endforeach
                </dl>
            </x-ui.panel>
            <x-ui.panel titulo="Conversa do chamado">
                @forelse ($mensagens as $mensagem)
                    <article class="row-summary">
                        <div>
                            <strong>{{ $mensagem->autor }}{{ $mensagem->interna ? ' · Nota interna' : '' }}</strong>
                            <p class="my-2">{{ $mensagem->mensagem }}</p>
                            <p class="small text-body-secondary mb-0">{{ $leituras->format($mensagem->criado_em, 'datetime') }}</p>
                        </div>
                    </article>
                @empty
                    <p class="text-body-secondary mb-0">Nenhuma mensagem disponível.</p>
                @endforelse
            </x-ui.panel>
            <x-ui.panel titulo="Histórico"><x-fleet.history :eventos="$eventos" /></x-ui.panel>
        </div>
        <div class="stack">
            <x-ui.panel titulo="Ações disponíveis">
                @forelse ($operacoes as $acao => $rotulo)
                    <a class="btn btn-outline-secondary w-100 mb-2" href="{{ route('tickets.operation', ['registro' => $chamado->id, 'acao' => $acao]) }}">{{ $rotulo }}</a>
                @empty
                    <p class="text-body-secondary mb-0">Nenhuma ação disponível para seu perfil e para a situação atual.</p>
                @endforelse
            </x-ui.panel>
            <x-ui.panel titulo="Anexos privados">
                @forelse ($anexos as $anexo)
                    <div class="attachment-row">
                        <span>{{ $anexo->nome_original }}</span>
                        <a href="{{ route('tickets.download', ['registro' => $chamado->id, 'anexo' => $anexo->id]) }}">Baixar anexo</a>
                    </div>
                @empty
                    <p class="text-body-secondary mb-0">Nenhum anexo autorizado disponível.</p>
                @endforelse
            </x-ui.panel>
        </div>
    </div>
</x-layouts.authenticated>
