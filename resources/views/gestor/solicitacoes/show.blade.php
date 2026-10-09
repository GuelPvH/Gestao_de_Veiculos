<x-layouts.authenticated :titulo="$tela['title'].' · Detalhes'" :breadcrumbs="[['label'=>$tela['title'], 'href'=>route('solicitacoes.index')], ['label'=>'Detalhes']]">
    <x-ui.title :titulo="($registro->protocolo ?? $registro->nome ?? $tela['title']).' · Detalhes'" subtitulo="Consulte as informações e o histórico deste registro.">
        <x-slot:acoes><a class="btn btn-outline-secondary" href="{{ route('solicitacoes.index') }}">Voltar à lista</a></x-slot:acoes>
    </x-ui.title>
    <div class="dashboard-grid">
        <div class="stack">
            <x-ui.panel titulo="Informações do registro"><dl class="detail-grid mb-0">
                @foreach($tela['columns'] + ($tela['details'] ?? []) as $chave => $campo)
                    <div><dt>{{ $campo[1] }}</dt><dd>@if($chave==='situacao')<x-ui.status :valor="$registro->situacao ?? ''" />@else{{ $valores[$chave] ?? '—' }}@endif</dd></div>
                @endforeach
            </dl></x-ui.panel>
            <x-ui.panel titulo="Histórico"><x-fleet.history :eventos="$eventos" /></x-ui.panel>
        </div>
        <div class="stack">
            <x-ui.panel titulo="Ações disponíveis">
                @forelse($operacoes as $acao=>$rotulo)
                    @if($acao === 'edit')
                        <a class="btn btn-outline-secondary w-100 mb-2" href="{{ route('solicitacoes.edit', $registro->id) }}">{{ $rotulo }}</a>
                    @elseif($acao === 'approve')
                        <form data-request-action="approve" id="approve-request" method="post" action="{{ route('solicitacoes.approve.submit', $registro->id) }}">
                            @csrf
                            <input type="hidden" name="modal_acao" value="approve">
                            <input type="hidden" name="versao" value="{{ $registro->versao }}">
                            <button type="button" class="btn btn-primary w-100 mb-2" data-request-approve>{{ $rotulo }}</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#request-{{ $acao }}">{{ $rotulo }}</button>
                    @endif
                @empty<p class="text-body-secondary mb-0">Nenhuma ação disponível para seu perfil e para a situação atual.</p>@endforelse
            </x-ui.panel>
            <x-ui.panel titulo="Documentos"><x-fleet.attachments :arquivos="$arquivos ?? []" /></x-ui.panel>
        </div>
    </div>
    @if($errors->any() && isset($operacoes[old('modal_acao', '')]))<span hidden data-request-reopen="{{ old('modal_acao') }}"></span>@endif
    @foreach($operacoes as $acao=>$rotulo)
        @if($acao !== 'edit')
            <x-ui.modal :id="'request-'.$acao" :titulo="$rotulo">
                @if($acao === 'approve')
                    <p>Confirma a aprovação da solicitação {{ $registro->protocolo }}? A aprovação programará a viagem com o veículo e motorista indicados no pedido.</p>
                    <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button><button type="submit" class="btn btn-primary" form="approve-request">Confirmar aprovação</button></div>
                @else
                    <form method="post" action="{{ route('solicitacoes.'.$acao.'.submit', $registro->id) }}">
                        @csrf
                        <input type="hidden" name="modal_acao" value="{{ $acao }}">
                        <input type="hidden" name="versao" value="{{ $registro->versao }}">
                        @if($acao === 'send')
                            <p>Confirma o envio desta solicitação para análise?</p>
                        @else
                            <x-forms.field :nome="'justificativa'" :id="'justificativa-'.$acao" :rotulo="$acao === 'deny' ? 'Motivo da negativa' : ($acao === 'adjust' ? 'Explique os ajustes necessários' : 'Motivo da nova revisão')" tipo="textarea" :obrigatorio="true" maxlength="3000" />
                        @endif
                        <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button><button type="submit" class="btn btn-primary">Confirmar</button></div>
                    </form>
                @endif
            </x-ui.modal>
        @endif
    @endforeach
</x-layouts.authenticated>
