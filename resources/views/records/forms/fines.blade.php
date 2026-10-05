@if($acao === 'create')
    <x-forms.field nome="veiculo_id" rotulo="Veículo autuado" tipo="select" :opcoes="$veiculosMulta ?? []" :obrigatorio="true" />
    @if(empty($veiculosMulta))<x-ui.alert tom="warning">Não há veículo ativo disponível para seu alcance.</x-ui.alert>@endif
    <x-forms.field nome="numero_auto" rotulo="Número do auto" maxlength="100" />
    <x-forms.field nome="orgao_autuador" rotulo="Órgão autuador" maxlength="150" />
    <x-forms.field nome="ocorrido_em" rotulo="Data e hora da ocorrência" tipo="datetime-local" :obrigatorio="true" />
    <x-forms.field nome="precisao_ocorrencia" rotulo="Precisão da autuação" tipo="select" :valor="'instante'" :opcoes="['instante'=>'Data e hora exatas','dia'=>'Somente o dia (hora ignorada)']" :obrigatorio="true" nota="Para autuação sem hora precisa, selecione somente o dia. A apuração considerará todo esse dia no fuso do órgão." />
    <x-forms.field nome="data_vencimento" rotulo="Vencimento" tipo="date" />
    <x-forms.field nome="valor" rotulo="Valor da multa" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="descricao" rotulo="Descrição da autuação" tipo="textarea" :obrigatorio="true" maxlength="3000" />
    <x-ui.alert tom="info">A abertura não atribui responsabilidade. Confira a viagem e o condutor antes de atribuí-la.</x-ui.alert>
@elseif($acao === 'edit')
    <x-ui.alert tom="info">Veículo e data da autuação são imutáveis. Esta edição está disponível somente antes da apuração.</x-ui.alert>
    <x-forms.field nome="numero_auto" rotulo="Número do auto" :valor="$registro->numero_auto" maxlength="100" />
    <x-forms.field nome="orgao_autuador" rotulo="Órgão autuador" :valor="$registro->orgao_autuador" maxlength="150" />
    <x-forms.field nome="data_vencimento" rotulo="Vencimento" tipo="date" :valor="$registro->data_vencimento" />
    <x-forms.field nome="valor" rotulo="Valor da multa" tipo="number" :valor="$registro->valor" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="descricao" rotulo="Descrição da autuação" tipo="textarea" :valor="$registro->descricao" :obrigatorio="true" maxlength="3000" />
    <x-forms.field nome="justificativa" rotulo="Justificativa da alteração" tipo="textarea" :obrigatorio="true" maxlength="3000" />
@elseif($acao === 'assign')
    <x-forms.field nome="viagem_id" rotulo="Viagem apurada" tipo="select" :opcoes="$viagensMulta ?? []" :obrigatorio="true" nota="A viagem deve ter saída real e coincidir com o veículo e o período da autuação." />
    @if(empty($viagensMulta))<x-ui.alert tom="warning">Não há viagem efetiva coincidente. Confira a data da autuação e o registro de saída.</x-ui.alert>@endif
    <x-forms.field nome="responsavel_id" rotulo="Responsável apurado" tipo="select" :opcoes="$responsaveisMulta ?? []" :obrigatorio="true" />
    <x-forms.field nome="justificativa" rotulo="Fundamento da apuração" tipo="textarea" :obrigatorio="true" maxlength="3000" />
    <x-ui.alert tom="info">O condutor da viagem é preservado no histórico. O responsável deve ser indicado expressamente após apuração.</x-ui.alert>
@elseif($acao === 'proof')
    <x-forms.field nome="comprovante" rotulo="Comprovante de pagamento" tipo="file" :obrigatorio="true" accept="application/pdf,image/png,image/jpeg" nota="PDF, PNG ou JPEG, até 10 MB. O arquivo ficará no armazenamento privado." />
    <x-forms.field nome="valor_declarado" rotulo="Valor declarado" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="pagamento_em" rotulo="Data e hora do pagamento" tipo="datetime-local" :obrigatorio="true" />
    <x-forms.field nome="observacao" rotulo="Observações" tipo="textarea" maxlength="2000" />
    <x-ui.alert tom="info">O envio cria uma versão imutável do comprovante. A quitação depende da conferência financeira.</x-ui.alert>
@elseif($acao === 'verify')
    <x-forms.field nome="resultado" rotulo="Resultado da conferência" tipo="select" :opcoes="['aceito'=>'Aceitar e quitar','correcao_solicitada'=>'Solicitar correção']" :obrigatorio="true" />
    <x-forms.field nome="valor_confirmado" rotulo="Valor confirmado (se aceito)" tipo="number" min="0.01" step="0.01" />
    <x-forms.field nome="pagamento_confirmado_em" rotulo="Data e hora real do pagamento (se aceito)" tipo="datetime-local" />
    <x-forms.field nome="motivo" rotulo="Motivo da correção ou da diferença de valor" tipo="textarea" maxlength="3000" />
    <x-ui.alert tom="warning">Somente o último comprovante pode ser conferido. O responsável e quem enviou o arquivo não podem conferir.</x-ui.alert>
@elseif($acao === 'correct')
    <x-forms.field nome="motivo" rotulo="Correção necessária" tipo="textarea" :obrigatorio="true" maxlength="3000" />
@elseif($acao === 'settle')
    <x-forms.field nome="valor_confirmado" rotulo="Valor confirmado" tipo="number" :obrigatorio="true" min="0.01" step="0.01" />
    <x-forms.field nome="pagamento_confirmado_em" rotulo="Data e hora real do pagamento" tipo="datetime-local" :obrigatorio="true" />
    <x-forms.field nome="motivo" rotulo="Justificativa se o valor difere da multa" tipo="textarea" maxlength="3000" />
    <x-ui.alert tom="warning">Confirmar a quitação cria o pagamento e impede edição direta do valor. Confira o comprovante mais recente.</x-ui.alert>
@elseif(in_array($acao, ['dispute', 'cancel'], true))
    <x-forms.field nome="justificativa" rotulo="Justificativa" tipo="textarea" :obrigatorio="true" maxlength="3000" />
    @if($acao === 'cancel')<x-ui.alert tom="warning">O cancelamento ficará registrado no histórico e não pode ser desfeito neste fluxo.</x-ui.alert>@endif
@endif
