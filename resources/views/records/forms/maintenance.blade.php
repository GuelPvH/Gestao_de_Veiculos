<x-forms.field nome="veiculo" rotulo="Veículo (placa)" :valor="$registro->placa ?? ''" :obrigatorio="true" maxlength="120" />
<x-forms.field nome="tipo" rotulo="Tipo de manutenção" tipo="select" :obrigatorio="true" :opcoes="['preventiva'=>'Preventiva','corretiva'=>'Corretiva','vistoria'=>'Vistoria']" />
<div class="row"><div class="col-md-6"><x-forms.field nome="inicio_previsto" rotulo="Início previsto" tipo="datetime-local" :obrigatorio="true" data-date-start /></div><div class="col-md-6"><x-forms.field nome="fim_previsto" rotulo="Fim previsto" tipo="datetime-local" :obrigatorio="true" data-date-end /></div></div>
<x-forms.field nome="descricao" rotulo="Serviços e peças previstos" tipo="textarea" :valor="$registro->descricao ?? ''" :obrigatorio="true" maxlength="3000" />
<x-forms.field nome="fornecedor" rotulo="Fornecedor" maxlength="150" />
<x-forms.field nome="quilometragem" rotulo="Quilometragem" tipo="number" min="0" step="0.1" />
<x-ui.alert tom="info">A manutenção deve bloquear a agenda do veículo durante o período confirmado.</x-ui.alert>
