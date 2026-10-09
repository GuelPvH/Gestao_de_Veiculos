<x-layouts.authenticated :titulo="$titulo" :breadcrumbs="[['label'=>$tela['title'],'href'=>route('solicitacoes.index')],['label'=>$titulo]]">
    <x-ui.title :titulo="$titulo" subtitulo="Preencha as informações e confira a revisão antes de concluir." />
    <x-ui.panel>
        <form id="operation-form" method="post" action="{{ $formAction }}" data-dirty-form data-review-form="operation-review" novalidate>@csrf
            @if($acao === 'edit')@method('PATCH')@endif
            @if($registro)<input type="hidden" name="versao" value="{{ $registro->versao }}">@endif
            @if(in_array($acao,['create','edit'],true))
                <ol class="wizard-steps" aria-label="Etapas da solicitação">@foreach(['Viagem','Pessoas','Veículo','Revisão'] as $etapa)<li data-step-label>{{ $loop->iteration }}. {{ $etapa }}</li>@endforeach</ol>
                <section class="form-stage"><h2 class="h5" tabindex="-1">Dados da viagem</h2>
                    <x-forms.field nome="finalidade" rotulo="Finalidade" tipo="textarea" :valor="$registro->finalidade ?? ''" :obrigatorio="true" maxlength="3000" />
                    <div class="row"><div class="col-md-6"><x-forms.field nome="origem" rotulo="Origem" :valor="$registro->origem ?? ''" :obrigatorio="true" maxlength="255" /></div><div class="col-md-6"><x-forms.field nome="destino" rotulo="Destino" :valor="$registro->destino ?? ''" :obrigatorio="true" maxlength="255" /></div></div>
                    <div class="row"><div class="col-md-6"><x-forms.field nome="saida_prevista" rotulo="Saída prevista" tipo="datetime-local" :valor="$registro && $registro->saida_prevista ? \Carbon\CarbonImmutable::parse($registro->saida_prevista,'UTC')->setTimezone(config('fleet.timezone'))->format('Y-m-d\TH:i') : ''" :obrigatorio="true" data-date-start /></div><div class="col-md-6"><x-forms.field nome="retorno_previsto" rotulo="Retorno previsto" tipo="datetime-local" :valor="$registro && $registro->retorno_previsto ? \Carbon\CarbonImmutable::parse($registro->retorno_previsto,'UTC')->setTimezone(config('fleet.timezone'))->format('Y-m-d\TH:i') : ''" :obrigatorio="true" data-date-end /></div></div>
                    <x-forms.field nome="trajeto_planejado" rotulo="Trajeto planejado" tipo="textarea" :valor="$registro->trajeto ?? ''" maxlength="3000" />
                </section>
                <section class="form-stage" hidden><h2 class="h5" tabindex="-1">Passageiros e condutor</h2>
                    <x-forms.field nome="quantidade_passageiros" rotulo="Quantidade de passageiros" tipo="number" :valor="$registro->passageiros ?? 1" :obrigatorio="true" min="1" max="100" step="1" />
                    <x-forms.field nome="passageiros" rotulo="Nomes dos passageiros" tipo="textarea" :valor="$registro->nomes_passageiros ?? ''" maxlength="16000" nota="Um nome por linha; a lista não pode exceder a quantidade informada." />
                    <x-forms.field nome="necessita_motorista" rotulo="Necessita de motorista?" tipo="select" :valor="$registro->necessita_motorista ?? 1" :obrigatorio="true" :opcoes="['1'=>'Sim','0'=>'Não']" />
                </section>
                <section class="form-stage" hidden><h2 class="h5" tabindex="-1">Preferências do veículo</h2>
                    <x-forms.field nome="veiculo_pretendido_id" rotulo="Veículo pretendido" tipo="select" :valor="$registro->veiculo_pretendido_id ?? ''" :opcoes="$veiculosDisponiveis ?? []" :obrigatorio="true" nota="A disponibilidade para o período solicitado será conferida na análise." />
                    @if(empty($veiculosDisponiveis))<x-ui.alert tom="warning">Não há veículo selecionável no alcance do perfil. Consulte o gestor da unidade.</x-ui.alert>@endif
                    <x-forms.field nome="observacoes" rotulo="Observações" tipo="textarea" :valor="$registro->observacoes ?? ''" maxlength="3000" />
                </section>
                <section class="form-stage" hidden><h2 class="h5" tabindex="-1">Revisão da solicitação</h2><p>Confira a viagem, as pessoas e as necessidades do veículo. Use Voltar para corrigir uma etapa e Revisar para visualizar todas as informações.</p><x-ui.alert tom="info">Alterações em solicitação aprovada precisam de nova análise.</x-ui.alert></section>
            @elseif($acao === 'send')
                <x-ui.alert tom="info">O envio congela a revisão atual e encaminha a solicitação para análise. Confira o rascunho antes de confirmar.</x-ui.alert>
            @else
                @include('gestor.solicitacoes.approval-fields')
                <x-forms.field nome="justificativa" rotulo="Justificativa" tipo="textarea" :obrigatorio="true" maxlength="3000" />
            @endif
            <div class="form-actions"><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ $registro ? route('solicitacoes.show',$registro->id) : route('solicitacoes.index') }}">Cancelar</a><button type="button" class="btn btn-outline-secondary" data-step-prev hidden>Voltar</button></div><div class="d-flex gap-2"><button type="button" class="btn btn-primary" data-step-next hidden>Continuar</button><button type="submit" class="btn btn-primary" data-review-submit>Revisar</button></div></div>
        </form>
    </x-ui.panel>
    <x-forms.review :confirmar="true" />
</x-layouts.authenticated>
