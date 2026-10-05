@if($acao==='approve')
    <x-forms.field nome="veiculo_confirmado_id" rotulo="Veículo confirmado" tipo="select" :opcoes="$veiculosDisponiveis ?? []" :obrigatorio="true" nota="A aprovação exige disponibilidade para todo o período solicitado." />
    <x-forms.field nome="motorista_confirmado_id" rotulo="Motorista confirmado" tipo="select" :opcoes="$motoristasDisponiveis ?? []" :obrigatorio="true" />
    <x-ui.alert tom="info">A aprovação programa a viagem. O registro de saída ocorre em uma etapa própria.</x-ui.alert>
@endif
