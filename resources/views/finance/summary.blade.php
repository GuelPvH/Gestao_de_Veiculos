<x-layouts.authenticated titulo="Resumo financeiro" :breadcrumbs="[['label' => 'Resumo financeiro']]">
    <x-ui.title
        titulo="Resumo financeiro"
        subtitulo="Consulte despesas registradas, pagamentos confirmados, saldo pendente e custos mensais por veículo."
    />
    <div class="metric-grid">
        <x-ui.metric
            rotulo="Despesas registradas"
            :valor="$leituras->format($totais['registradas'], 'money')"
            nota="Despesas não canceladas no filtro"
        />
        <x-ui.metric
            rotulo="Despesas pagas"
            :valor="$leituras->format($totais['pagas'], 'money')"
            nota="Pagamentos confirmados"
        />
        <x-ui.metric
            rotulo="Saldo pendente"
            :valor="$leituras->format($totais['pendente'], 'money')"
            nota="Registradas menos pagas"
        />
    </div>
    <x-ui.panel titulo="Filtros">
        <form method="get" action="{{ route('finance.summary') }}" class="filter-grid">
            <x-forms.field
                nome="veiculo"
                rotulo="Veículo"
                tipo="select"
                :valor="$filtros['veiculo']"
                :opcoes="$opcoesVeiculos"
                placeholder="Todos os veículos"
            />
            <x-forms.field nome="de" rotulo="Mês inicial" tipo="month" :valor="$filtros['de']" />
            <x-forms.field nome="ate" rotulo="Mês final" tipo="month" :valor="$filtros['ate']" />
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-link" href="{{ route('finance.summary') }}">Limpar</a>
            </div>
        </form>
    </x-ui.panel>
    <x-ui.panel titulo="Resumo por veículo">
        @if ($resumo === [])
            <x-ui.empty
                titulo="Nenhum veículo encontrado"
                descricao="Não há veículos com dados financeiros para os filtros selecionados."
            />
        @else
            <div class="table-responsive" tabindex="0" role="region" aria-label="Resumo financeiro por veículo">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Placa</th>
                            <th scope="col">Registradas</th>
                            <th scope="col">Pagas</th>
                            <th scope="col">Saldo pendente</th>
                            <th scope="col"><span class="visually-hidden">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumo as $linha)
                            <tr>
                                <td>{{ $linha['placa'] }}</td>
                                <td>{{ $leituras->format($linha['despesas_registradas'], 'money') }}</td>
                                <td>{{ $leituras->format($linha['despesas_pagas'], 'money') }}</td>
                                <td>{{ $leituras->format($linha['saldo_pendente'], 'money') }}</td>
                                <td>
                                    <a
                                        class="btn btn-outline-secondary btn-sm"
                                        href="{{ route('finance.summary', array_filter(['veiculo' => $linha['placa'], 'de' => $filtros['de'], 'ate' => $filtros['ate']])) }}"
                                        >Custos mensais</a
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.panel>
    @if ($mensal !== null)
        <x-ui.panel :titulo="'Custos mensais · '.$filtros['veiculo']">
            @if ($mensal['meses'] === [])
                <x-ui.empty
                    titulo="Nenhuma despesa no período"
                    descricao="Não há despesas não canceladas registradas para este veículo no intervalo informado."
                />
            @else
                <div class="table-responsive" tabindex="0" role="region" aria-label="Custos mensais do veículo">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Mês</th>
                                <th scope="col">Quantidade de despesas</th>
                                <th scope="col">Valor total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mensal['meses'] as $mes)
                                <tr>
                                    <td>{{ $mes['mes'] }}</td>
                                    <td>{{ $mes['quantidade_despesas'] }}</td>
                                    <td>{{ $leituras->format($mes['valor_despesas'], 'money') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row" colspan="2">Total no período</th>
                                <td><strong>{{ $leituras->format($mensal['total'], 'money') }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-ui.panel>
    @endif
</x-layouts.authenticated>
