@if(in_array($acao,['create','edit'],true))
    <x-forms.field nome="veiculo" rotulo="Veículo (placa)" :valor="$registro->veiculo ?? ''" :obrigatorio="true" maxlength="120" />
    <x-forms.field nome="categoria" rotulo="Categoria da despesa" tipo="select" :obrigatorio="true" :opcoes="$categoriasDespesa ?? []" />
    <div class="row"><div class="col-md-6"><x-forms.field nome="data_despesa" rotulo="Data da despesa" tipo="date" :obrigatorio="true" /></div><div class="col-md-6"><x-forms.field nome="valor" rotulo="Valor" tipo="number" :obrigatorio="true" min="0.01" step="0.01" /></div></div>
    <x-forms.field nome="fornecedor" rotulo="Fornecedor" :valor="$registro->fornecedor ?? ''" maxlength="150" />
    <x-forms.field nome="numero_documento" rotulo="Número do documento" :valor="$registro->numero_documento ?? ''" maxlength="100" />
    <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$registro->descricao ?? ''" :obrigatorio="true" maxlength="3000" />
    <x-forms.field nome="documento" rotulo="Documento fiscal" tipo="file" accept="application/pdf,image/png,image/jpeg" nota="Até 10 MB por arquivo." />
@elseif($acao==='verify')
    <x-forms.field nome="resultado" rotulo="Resultado da conferência" tipo="select" :obrigatorio="true" :opcoes="['aceito'=>'Aceitar','correcao_solicitada'=>'Solicitar correção']" />
    <x-forms.field nome="valor_confirmado" rotulo="Valor confirmado" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="pago_em" rotulo="Data de pagamento" tipo="datetime-local" :obrigatorio="true" />
@endif
