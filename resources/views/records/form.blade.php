<x-layouts.authenticated
    :titulo="$titulo"
    :breadcrumbs="[['label'=>$tela['title'],'href'=>route($codigo.'.index')],['label'=>$titulo]]"
>
    <x-ui.title :titulo="$titulo" subtitulo="Preencha as informações e confira a revisão antes de concluir." />
    @if ($codigo === 'roles' && $acao === 'grant' && $opcoesRotas !== [])
        <x-ui.panel titulo="Selecionar rota técnica">
            <form
                method="get"
                action="{{ route('roles.operation', ['registro' => $registro->id, 'acao' => 'grant']) }}"
            >
                <x-forms.field
                    nome="rota"
                    rotulo="Rota técnica"
                    tipo="select"
                    :opcoes="$opcoesRotas"
                    :valor="$rotaSelecionada"
                    placeholder="Todas as permissões delegáveis"
                />
                <button type="submit" class="btn btn-outline-secondary">Mostrar permissões da rota</button>
            </form>
            <p class="form-text mb-0">A concessão usa a ação e o alcance do perfil. Ela se aplica às rotas que exigem essa mesma ação.</p>
        </x-ui.panel>
    @endif
    @php ($semOpcoesAdmin = isset($opcoesAdmin) && $opcoesAdmin === [] && in_array($codigo.'.'.$acao, ['users.revoke', 'roles.grant', 'roles.revoke'], true))
    <x-ui.panel>
        @if ($semOpcoesAdmin)
            <x-ui.alert tom="info">Nenhum vínculo ou permissão disponível para esta ação.</x-ui.alert>
        @endif
        <form
            id="operation-form"
            method="post"
            action="{{ $formAction ?? (($codigo === 'trips' ? route('trips.perform', ['registro' => $registro->id, 'acao' => $acao]) : (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) ? ($registro ? route($codigo.'.perform', ['registro' => $registro->id, 'acao' => $acao]) : route($codigo.'.store')) : url()->current()))) }}"
            @if (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true) || ($codigo === 'fines' && $acao === 'proof')) enctype="multipart/form-data" @endif
            data-dirty-form
            data-review-form="operation-review"
            novalidate
        >
            @csrf
            @if (in_array($codigo, ['requests', 'trips', 'vehicles', 'expenses', 'fuel', 'maintenance', 'fines', 'users'], true) && $registro)
                <input type="hidden" name="versao" value="{{ $registro->versao ?? 1 }}" />
            @endif
            @if ($codigo === 'roles' && $registro)
                <input type="hidden" name="atualizado_em" value="{{ $registro->atualizado_em ?? '' }}" />
            @endif
            @if ($codigo === 'trips' && $acao === 'cancel')
                <input type="hidden" name="solicitacao_versao" value="{{ $registro->solicitacao_versao }}" />
            @endif
            @if ($codigo === 'vehicles')
                @include ('records.forms.vehicles')
            @elseif ($codigo === 'trips' && in_array($acao,['departure','return'],true))
                <x-forms.field
                    nome="data_registro"
                    rotulo="{{ $acao==='departure' ? 'Data e hora da saída' : 'Data e hora do retorno' }}"
                    tipo="datetime-local"
                    :obrigatorio="true"
                />
                <x-forms.field
                    nome="quilometragem"
                    rotulo="Quilometragem do veículo"
                    tipo="number"
                    :obrigatorio="true"
                    :min="$registro->quilometragem_saida ?? 0"
                    step="0.1"
                />
                <fieldset>
                    <legend class="h5">Vistoria {{ $acao==='departure' ? 'de saída' : 'de retorno' }}</legend>
                    @foreach ($checklist ?? [] as $item)
                        <div class="row">
                            <div class="col-md-6">
                                <x-forms.field
                                    :nome="'item_'.$item->id"
                                    :rotulo="$item->descricao"
                                    tipo="select"
                                    :obrigatorio="(bool)$item->obrigatorio"
                                    :opcoes="['ok'=>'Em ordem','problema'=>'Problema identificado','nao_aplicavel'=>'Não se aplica']"
                                    data-checklist-result
                                />
                            </div>
                            <div class="col-md-6">
                                <x-forms.field
                                    :nome="'observacao_'.$item->id"
                                    :rotulo="'Observação: '.$item->descricao"
                                    maxlength="1000"
                                    data-checklist-note
                                />
                            </div>
                        </div>
                    @endforeach
                </fieldset>
                <x-forms.field nome="observacoes" rotulo="Observações da vistoria" tipo="textarea" maxlength="3000" />
            @elseif ($acao === 'occurrence')
                <x-forms.field
                    nome="tipo"
                    rotulo="Tipo de ocorrência"
                    tipo="select"
                    :obrigatorio="true"
                    :opcoes="['geral'=>'Geral','desvio_trajeto'=>'Desvio de trajeto','avaria'=>'Avaria','acidente'=>'Acidente','atraso'=>'Atraso']"
                />
                <x-forms.field nome="ocorrido_em" rotulo="Data e hora" tipo="datetime-local" :obrigatorio="true" />
                <x-forms.field
                    nome="descricao"
                    rotulo="Descrição da ocorrência"
                    tipo="textarea"
                    :obrigatorio="true"
                    maxlength="3000"
                />
            @elseif (in_array($codigo, ['expenses', 'fuel', 'maintenance'], true))
                @include ('records.forms.finance')
            @elseif ($codigo === 'fines')
                @include ('records.forms.fines')
            @elseif ($acao === 'proof')
                <x-forms.field
                    nome="comprovante"
                    rotulo="Comprovante de pagamento"
                    tipo="file"
                    :obrigatorio="true"
                    accept="application/pdf,image/png,image/jpeg"
                    nota="PDF, PNG ou JPEG, até 10 MB. O arquivo será exibido na revisão."
                />
                <x-forms.field
                    nome="valor_declarado"
                    rotulo="Valor declarado"
                    tipo="number"
                    :obrigatorio="true"
                    min="0.01"
                    step="0.01"
                />
                <x-forms.field
                    nome="pagamento_em"
                    rotulo="Data e hora do pagamento"
                    tipo="datetime-local"
                    :obrigatorio="true"
                />
                <x-forms.field nome="observacao" rotulo="Observações" tipo="textarea" maxlength="2000" />
            @else
                @includeIf ('records.forms.'.$codigo)
                @if (! in_array($acao, ['create', 'grant', 'revoke'], true))
                    <x-forms.field
                        nome="justificativa"
                        rotulo="Justificativa"
                        tipo="textarea"
                        :obrigatorio="true"
                        maxlength="3000"
                    />
                @endif
            @endif
            <div class="form-actions">
                <div class="d-flex gap-2">
                    <a
                        class="btn btn-outline-secondary"
                        href="{{ $registro ? route($codigo.'.show',$registro->id) : route($codigo.'.index') }}"
                        >Cancelar</a
                    ><button type="button" class="btn btn-outline-secondary" data-step-prev hidden>Voltar</button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-step-next hidden>Continuar</button
                    ><button type="submit" class="btn btn-primary" data-review-submit @disabled ($semOpcoesAdmin)>
                        Revisar
                    </button>
                </div>
            </div>
        </form>
    </x-ui.panel>
    <x-forms.review
        :confirmar="in_array($codigo, ['requests', 'trips', 'vehicles', 'expenses', 'fuel', 'maintenance', 'fines', 'users', 'roles', 'technical-routes'], true)"
    />
</x-layouts.authenticated>
