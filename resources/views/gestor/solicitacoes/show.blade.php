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
                    @else
                        <button type="button" class="btn btn-outline-secondary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#request-{{ $acao }}">{{ $rotulo }}</button>
                    @endif
                @empty
                    @if($registro->situacao === 'aguardando_analise' && (int) $registro->__owner === (int) $acesso->link()->usuario_id)
                        <p class="text-body-secondary mb-0">Você não pode aprovar, negar ou solicitar ajustes na própria solicitação, mesmo usando o perfil gestor. Outro gestor autorizado da unidade precisa realizar a análise.</p>
                    @else
                        <p class="text-body-secondary mb-0">Nenhuma ação disponível para seu perfil e para a situação atual.</p>
                    @endif
                @endforelse
            </x-ui.panel>
            <x-ui.panel titulo="Documentos"><x-fleet.attachments :arquivos="$arquivos ?? []" /></x-ui.panel>
        </div>
    </div>
    @if($errors->any() && isset($operacoes[old('modal_acao', '')]))<span hidden data-request-reopen="{{ old('modal_acao') }}"></span>@endif
    @foreach($operacoes as $acao=>$rotulo)
        @if($acao !== 'edit')
            <x-ui.modal :id="'request-'.$acao" :titulo="$rotulo">
                    <form method="post" action="{{ route('solicitacoes.'.$acao.'.submit', $registro->id) }}">
                        @csrf
                        <input type="hidden" name="modal_acao" value="{{ $acao }}">
                        <input type="hidden" name="versao" value="{{ $registro->versao }}">
                        @if($acao === 'approve')
                            @include('gestor.solicitacoes.approval-fields')
                            <x-forms.field nome="justificativa" rotulo="Justificativa da aprovação" tipo="textarea" :obrigatorio="true" maxlength="3000" />
                        @elseif($acao === 'send')
                            <p>Confirma o envio desta solicitação para análise?</p>
                        @else
                            <x-forms.field :nome="'justificativa'" :id="'justificativa-'.$acao" :rotulo="$acao === 'deny' ? 'Motivo da negativa' : ($acao === 'adjust' ? 'Explique os ajustes necessários' : 'Motivo da nova revisão')" tipo="textarea" :obrigatorio="true" maxlength="3000" />
                        @endif
                        <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button><button type="submit" class="btn btn-primary">{{ $acao === 'approve' ? 'Confirmar aprovação' : 'Confirmar' }}</button></div>
                    </form>
            </x-ui.modal>
        @endif
    @endforeach
</x-layouts.authenticated>
