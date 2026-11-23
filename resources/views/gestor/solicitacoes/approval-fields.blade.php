@if ($acao==='approve')
    @if (empty($veiculosDisponiveis))
        <x-ui.alert tom="warning">
            Nenhum veículo disponível sem conflito de agenda e com capacidade suficiente para este período.
        </x-ui.alert>
    @endif
    <x-forms.field
        nome="veiculo_confirmado_id"
        rotulo="Veículo confirmado"
        tipo="select"
        :opcoes="$veiculosDisponiveis ?? []"
        :obrigatorio="true"
        nota="Lista apenas veículos ativos, com capacidade suficiente e livres no período solicitado."
    />
    @if (empty($motoristasDisponiveis))
        <x-ui.alert tom="warning">
            Nenhum motorista ativo com CNH válida e livre no período foi encontrado.
            @if (Route::has('drivers.create') && ($acesso->level('frota', 'gerenciar') > 0 || $acesso->level('usuarios', 'editar') > 0))
                <a href="{{ route('drivers.create') }}">Cadastrar motorista</a
                >.
            @endif
        </x-ui.alert>
    @endif
    <x-forms.field
        nome="motorista_confirmado_id"
        rotulo="Motorista confirmado"
        tipo="select"
        :opcoes="$motoristasDisponiveis ?? []"
        :obrigatorio="true"
        nota="A categoria de CNH do motorista deve atender à categoria exigida pelo veículo escolhido."
    />
    <x-ui.alert tom="info">A aprovação programa a viagem. O registro de saída ocorre em uma etapa própria.</x-ui.alert>
@endif
