@if($acao==='create')
    <x-forms.field nome="veiculo" rotulo="Veículo (placa)" :obrigatorio="true" maxlength="120" />
    <x-forms.field nome="numero_auto" rotulo="Número do auto" maxlength="100" />
    <x-forms.field nome="orgao_autuador" rotulo="Órgão autuador" maxlength="150" />
    <x-forms.field nome="ocorrido_em" rotulo="Data e hora da ocorrência" tipo="datetime-local" :obrigatorio="true" />
    <x-forms.field nome="valor" rotulo="Valor" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :obrigatorio="true" maxlength="3000" />
@elseif($acao==='assign')
    <x-forms.field nome="viagem" rotulo="Protocolo da viagem apurada" :obrigatorio="true" maxlength="40" />
    <x-forms.field nome="motorista" rotulo="Condutor da viagem" :obrigatorio="true" maxlength="150" />
    <x-forms.field nome="responsavel" rotulo="Responsável apurado" :obrigatorio="true" maxlength="150" />
    <x-ui.alert tom="info">A responsabilidade depende da apuração da viagem e do condutor. Não é atribuída automaticamente ao solicitante.</x-ui.alert>
@elseif(in_array($acao,['verify','settle'],true))
    <x-forms.field nome="valor_confirmado" rotulo="Valor confirmado" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="pagamento_confirmado_em" rotulo="Data e hora do pagamento" tipo="datetime-local" :obrigatorio="true" />
    <x-ui.alert tom="info">Um comprovante enviado precisa de conferência aceita antes de confirmar a quitação.</x-ui.alert>
@elseif($acao==='correct')<p>Descreva o que precisa ser corrigido no comprovante. A solicitação de correção mantém o histórico dos envios.</p>@endif
