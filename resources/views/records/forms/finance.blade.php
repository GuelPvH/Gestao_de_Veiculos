@if($codigo === 'expenses')
    @if(in_array($acao, ['create', 'edit'], true))
        @if($acao === 'create')
            <x-forms.field nome="veiculo" rotulo="Placa do veículo" :obrigatorio="true" maxlength="20" />
            <x-forms.field nome="categoria" rotulo="Categoria da despesa" tipo="select" :opcoes="$categoriasDespesa ?? []" :obrigatorio="true" />
        @else
            <p>Veículo: <strong>{{ $registro->placa }}</strong></p>
        @endif
        <div class="row"><div class="col-md-6"><x-forms.field nome="data_despesa" rotulo="Data da despesa" tipo="date" :valor="$registro->data_despesa ?? ''" :obrigatorio="true" /></div><div class="col-md-6"><x-forms.field nome="valor" rotulo="Valor (R$)" tipo="number" :valor="$registro->valor ?? ''" :obrigatorio="true" min="0.01" step="0.01" /></div></div>
        <x-forms.field nome="fornecedor" rotulo="Fornecedor" :valor="$registro->fornecedor ?? ''" maxlength="150" />
        <x-forms.field nome="numero_documento" rotulo="Número do documento" :valor="$registro->numero_documento ?? ''" maxlength="100" />
        <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$registro->descricao ?? ''" :obrigatorio="true" maxlength="3000" />
        <x-forms.field nome="documento" rotulo="Documento fiscal" tipo="file" accept="application/pdf,image/png,image/jpeg" nota="PDF, PNG ou JPEG, até 10 MB. O original fica em armazenamento privado." />
    @elseif($acao === 'submit')
        <x-ui.alert tom="info">O envio encaminha a despesa registrada para conferência.</x-ui.alert>
    @elseif($acao === 'verify')
        <x-forms.field nome="resultado" rotulo="Resultado da conferência" tipo="select" :obrigatorio="true" :opcoes="['aceito'=>'Aprovar','correcao_solicitada'=>'Solicitar correção']" />
        <x-forms.field nome="justificativa" rotulo="Justificativa para correção" tipo="textarea" maxlength="3000" nota="Obrigatória quando solicitar correção." />
    @elseif($acao === 'pay')
        <x-ui.alert tom="warning">Confirme o pagamento somente após conferir a data e o comprovante. O pagamento registrado é imutável.</x-ui.alert>
        <x-forms.field nome="pago_em" rotulo="Data e hora do pagamento" tipo="datetime-local" :obrigatorio="true" />
        <x-forms.field nome="comprovante" rotulo="Comprovante de pagamento" tipo="file" :obrigatorio="true" accept="application/pdf,image/png,image/jpeg" nota="PDF, PNG ou JPEG, até 10 MB." />
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmar" value="1" id="finance-confirmar" required><label class="form-check-label" for="finance-confirmar">Confirmo o pagamento e o comprovante.</label></div>
    @elseif($acao === 'cancel')
        <x-forms.field nome="justificativa" rotulo="Justificativa do cancelamento" tipo="textarea" :obrigatorio="true" maxlength="3000" />
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmar" value="1" id="finance-confirmar" required><label class="form-check-label" for="finance-confirmar">Confirmo o cancelamento desta despesa.</label></div>
    @endif
@elseif($codigo === 'fuel')
    @if($acao === 'create')<x-forms.field nome="veiculo" rotulo="Placa do veículo" :obrigatorio="true" maxlength="20" />@else<p>Veículo: <strong>{{ $registro->placa }}</strong></p>@endif
    <x-forms.field nome="combustivel" rotulo="Combustível" tipo="select" :valor="$registro->combustivel ?? ''" :obrigatorio="true" :opcoes="['gasolina'=>'Gasolina','etanol'=>'Etanol','diesel'=>'Diesel','gnv'=>'GNV','eletricidade'=>'Eletricidade','outro'=>'Outro']" data-fuel-type />
    <div class="row"><div class="col-md-6"><x-forms.field nome="quantidade" rotulo="Quantidade" tipo="number" :valor="$registro->quantidade ?? ''" :obrigatorio="true" min="0.001" step="0.001" /></div><div class="col-md-6"><x-forms.field nome="unidade_medida" rotulo="Unidade de medida" tipo="select" :valor="$registro->unidade_medida ?? ''" :obrigatorio="true" :opcoes="['litro'=>'Litro','m3'=>'Metro cúbico','kwh'=>'Quilowatt-hora']" data-fuel-unit /></div></div>
    <div class="row"><div class="col-md-6"><x-forms.field nome="preco_unitario" rotulo="Preço unitário (R$)" tipo="number" :valor="$registro->preco_unitario ?? ''" :obrigatorio="true" min="0.0001" step="0.0001" /></div><div class="col-md-6"><x-forms.field nome="quilometragem" rotulo="Quilometragem" tipo="number" :valor="$registro->quilometragem ?? ''" :obrigatorio="true" min="0" step="0.1" /></div></div>
    <x-forms.field nome="data" rotulo="Data do abastecimento" tipo="date" :valor="$registro->data_despesa ?? ''" :obrigatorio="true" />
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="tanque_completo" value="1" id="fuel-completo" @checked(old('tanque_completo', $registro->tanque_completo ?? 0))><label class="form-check-label" for="fuel-completo">Tanque completo</label></div>
    <x-forms.field nome="fornecedor" rotulo="Fornecedor" :valor="$registro->fornecedor ?? ''" maxlength="150" />
    <x-forms.field nome="numero_documento" rotulo="Número do documento" :valor="$registro->numero_documento ?? ''" maxlength="100" />
    <x-forms.field nome="documento" rotulo="Documento fiscal" tipo="file" accept="application/pdf,image/png,image/jpeg" nota="PDF, PNG ou JPEG, até 10 MB." />
@elseif($codigo === 'maintenance')
    @if(in_array($acao, ['create', 'edit'], true))
        @if($acao === 'create')<x-forms.field nome="veiculo" rotulo="Placa do veículo" :obrigatorio="true" maxlength="20" />@else<p>Veículo: <strong>{{ $registro->placa }}</strong></p>@endif
        <x-forms.field nome="tipo" rotulo="Tipo de manutenção" tipo="select" :valor="$registro->tipo ?? ''" :obrigatorio="true" :opcoes="['preventiva'=>'Preventiva','corretiva'=>'Corretiva','vistoria'=>'Vistoria']" />
        <div class="row"><div class="col-md-6"><x-forms.field nome="inicio_previsto" rotulo="Início previsto" tipo="datetime-local" :valor="$registro && $registro->inicio_previsto ? \Carbon\CarbonImmutable::parse($registro->inicio_previsto,'UTC')->setTimezone(config('fleet.timezone'))->format('Y-m-d\TH:i') : ''" :obrigatorio="true" data-date-start /></div><div class="col-md-6"><x-forms.field nome="fim_previsto" rotulo="Fim previsto" tipo="datetime-local" :valor="$registro && $registro->fim_previsto ? \Carbon\CarbonImmutable::parse($registro->fim_previsto,'UTC')->setTimezone(config('fleet.timezone'))->format('Y-m-d\TH:i') : ''" :obrigatorio="true" data-date-end /></div></div>
        <x-forms.field nome="descricao" rotulo="Serviços e peças previstos" tipo="textarea" :valor="$registro->descricao ?? ''" :obrigatorio="true" maxlength="3000" />
        <x-forms.field nome="fornecedor" rotulo="Fornecedor" :valor="$registro->fornecedor ?? ''" maxlength="150" />
        <x-forms.field nome="quilometragem" rotulo="Quilometragem" tipo="number" :valor="$registro->quilometragem ?? ''" min="0" step="0.1" />
        <x-forms.field nome="documento" rotulo="Documento da ordem" tipo="file" accept="application/pdf,image/png,image/jpeg" />
        <x-ui.alert tom="info">A manutenção reserva o veículo na agenda durante o período previsto.</x-ui.alert>
    @elseif($acao === 'start')
        <x-forms.field nome="inicio_real" rotulo="Início real" tipo="datetime-local" :obrigatorio="true" />
    @elseif($acao === 'complete')
        <x-forms.field nome="fim_real" rotulo="Conclusão real" tipo="datetime-local" :obrigatorio="true" />
        <x-forms.field nome="quilometragem" rotulo="Quilometragem final" tipo="number" :valor="$registro->quilometragem ?? ''" min="0" step="0.1" />
        <x-forms.field nome="proxima_revisao_km" rotulo="Próxima revisão (km)" tipo="number" min="0" step="0.1" />
        <x-forms.field nome="proxima_revisao_data" rotulo="Próxima revisão (data)" tipo="date" />
    @elseif($acao === 'cancel')
        <x-forms.field nome="justificativa" rotulo="Justificativa do cancelamento" tipo="textarea" :obrigatorio="true" maxlength="1000" />
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmar" value="1" id="finance-confirmar" required><label class="form-check-label" for="finance-confirmar">Confirmo o cancelamento desta manutenção.</label></div>
    @endif
@endif
