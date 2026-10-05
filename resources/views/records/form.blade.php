<x-layouts.authenticated :titulo="$titulo" :breadcrumbs="[['label'=>$tela['title'],'href'=>route($codigo.'.index')],['label'=>$titulo]]">
    <x-ui.title :titulo="$titulo" subtitulo="Preencha as informações e confira a revisão antes de concluir." />
    <x-ui.panel>
        <form id="operation-form" method="post" action="{{ $formAction ?? ($codigo === 'requests' ? ($registro ? route('requests.perform', ['registro' => $registro->id, 'acao' => $acao]) : route('requests.store')) : ($codigo === 'trips' ? route('trips.perform', ['registro' => $registro->id, 'acao' => $acao]) : (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) ? ($registro ? route($codigo.'.perform', ['registro' => $registro->id, 'acao' => $acao]) : route($codigo.'.store')) : url()->current()))) }}" @if(in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) || ($codigo === 'fines' && $acao === 'proof')) enctype="multipart/form-data" @endif data-dirty-form data-review-form="operation-review" novalidate>@csrf
            @if(in_array($codigo, ['requests', 'trips', 'vehicles', 'expenses', 'fuel', 'maintenance', 'fines', 'users'], true) && $registro)<input type="hidden" name="versao" value="{{ $registro->versao }}">@endif
            @if($codigo === 'roles' && $registro)<input type="hidden" name="atualizado_em" value="{{ $registro->atualizado_em }}">@endif
            @if($codigo === 'trips' && $acao === 'cancel')<input type="hidden" name="solicitacao_versao" value="{{ $registro->solicitacao_versao }}">@endif
            @if($codigo === 'requests' && in_array($acao,['create','edit'],true))
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
            @elseif($codigo === 'requests' && $acao === 'send')
                <x-ui.alert tom="info">O envio congela a revisão atual e encaminha a solicitação para análise. Confira o rascunho antes de confirmar.</x-ui.alert>
            @elseif($codigo === 'vehicles')
                @include('records.forms.vehicles')
            @elseif($codigo === 'trips' && in_array($acao,['departure','return'],true))
                <x-forms.field nome="data_registro" rotulo="{{ $acao==='departure' ? 'Data e hora da saída' : 'Data e hora do retorno' }}" tipo="datetime-local" :obrigatorio="true" />
                <x-forms.field nome="quilometragem" rotulo="Quilometragem do veículo" tipo="number" :obrigatorio="true" :min="$registro->quilometragem_saida ?? 0" step="0.1" />
                <fieldset><legend class="h5">Vistoria {{ $acao==='departure' ? 'de saída' : 'de retorno' }}</legend>
                    @foreach($checklist ?? [] as $item)<div class="row"><div class="col-md-6"><x-forms.field :nome="'item_'.$item->id" :rotulo="$item->descricao" tipo="select" :obrigatorio="(bool)$item->obrigatorio" :opcoes="['ok'=>'Em ordem','problema'=>'Problema identificado','nao_aplicavel'=>'Não se aplica']" data-checklist-result /></div><div class="col-md-6"><x-forms.field :nome="'observacao_'.$item->id" :rotulo="'Observação: '.$item->descricao" maxlength="1000" data-checklist-note /></div></div>@endforeach
                </fieldset><x-forms.field nome="observacoes" rotulo="Observações da vistoria" tipo="textarea" maxlength="3000" />
            @elseif($acao === 'occurrence')
                <x-forms.field nome="tipo" rotulo="Tipo de ocorrência" tipo="select" :obrigatorio="true" :opcoes="['geral'=>'Geral','desvio_trajeto'=>'Desvio de trajeto','avaria'=>'Avaria','acidente'=>'Acidente','atraso'=>'Atraso']" />
                <x-forms.field nome="ocorrido_em" rotulo="Data e hora" tipo="datetime-local" :obrigatorio="true" />
                <x-forms.field nome="descricao" rotulo="Descrição da ocorrência" tipo="textarea" :obrigatorio="true" maxlength="3000" />
            @elseif(in_array($codigo, ['expenses', 'fuel', 'maintenance'], true))
                @include('records.forms.finance')
            @elseif($codigo === 'fines')
                @include('records.forms.fines')
            @elseif($acao === 'proof')
                <x-forms.field nome="comprovante" rotulo="Comprovante de pagamento" tipo="file" :obrigatorio="true" accept="application/pdf,image/png,image/jpeg" nota="PDF, PNG ou JPEG, até 10 MB. O arquivo será exibido na revisão." />
                <x-forms.field nome="valor_declarado" rotulo="Valor declarado" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
                <x-forms.field nome="pagamento_em" rotulo="Data e hora do pagamento" tipo="datetime-local" :obrigatorio="true" />
                <x-forms.field nome="observacao" rotulo="Observações" tipo="textarea" maxlength="2000" />
            @else
                @includeIf('records.forms.'.$codigo)
                @if($acao !== 'create')
                    <x-forms.field nome="justificativa" rotulo="Justificativa" tipo="textarea" :obrigatorio="true" maxlength="3000" />
                @endif
            @endif
            <div class="form-actions"><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ $registro ? route($codigo.'.show',$registro->id) : route($codigo.'.index') }}">Cancelar</a><button type="button" class="btn btn-outline-secondary" data-step-prev hidden>Voltar</button></div><div class="d-flex gap-2"><button type="button" class="btn btn-primary" data-step-next hidden>Continuar</button><button type="submit" class="btn btn-primary" data-review-submit>Revisar</button></div></div>
        </form>
    </x-ui.panel>
    <x-forms.review :confirmar="in_array($codigo, ['requests', 'trips', 'vehicles', 'expenses', 'fuel', 'maintenance', 'fines', 'users', 'roles', 'technical-routes'], true)" />
</x-layouts.authenticated>
