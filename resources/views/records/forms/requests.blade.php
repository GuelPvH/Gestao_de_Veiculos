@if($acao==='approve')
    <x-forms.field nome="veiculo_confirmado" rotulo="Veículo confirmado" :obrigatorio="true" maxlength="120" nota="A aprovação exige disponibilidade para todo o período solicitado." />
    <x-forms.field nome="motorista_confirmado" rotulo="Motorista confirmado" :obrigatorio="true" maxlength="150" />
    <x-ui.alert tom="info">A aprovação programa a viagem. O registro de saída ocorre em uma etapa própria.</x-ui.alert>
@endif
