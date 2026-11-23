<x-layouts.authenticated titulo="Motoristas" :breadcrumbs="[['label' => 'Motoristas']]">
    <x-ui.title
        titulo="Motoristas"
        subtitulo="Gerencie os condutores habilitados, categorias de CNH e validade da habilitação."
    >
        <x-slot:acoes>
            @if ($podeGerenciar)
                <a class="btn btn-primary" href="{{ route('drivers.create') }}">Novo motorista</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>

    <x-ui.panel>
        <form method="get" action="{{ route('drivers.index') }}" class="filter-grid">
            <x-forms.field
                nome="q"
                rotulo="Buscar por nome, CPF, CNH ou unidade"
                :valor="$filtros['q'] ?? ''"
                maxlength="150"
            />
            <x-forms.field
                nome="situacao"
                rotulo="Situação"
                tipo="select"
                :valor="$filtros['situacao'] ?? ''"
                :opcoes="['1' => 'Ativo', '0' => 'Inativo']"
            />
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-link" href="{{ route('drivers.index') }}">Limpar</a>
            </div>
        </form>

        @if ($paginacao->isEmpty())
            <x-ui.empty
                titulo="Nenhum motorista cadastrado"
                descricao="Cadastre condutores com CNH válida para permitir aprovação de solicitações e condução própria."
            />
        @else
            @php
                $hoje = now(config('fleet.timezone'))->toDateString();
            @endphp
            <div class="table-responsive" tabindex="0" role="region" aria-label="Lista de motoristas">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Motorista</th>
                            <th scope="col">Unidade</th>
                            <th scope="col">CNH / Categorias</th>
                            <th scope="col">Validade</th>
                            <th scope="col">Situação</th>
                            <th scope="col">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paginacao as $motorista)
                            @php
                                $vencida = $motorista->validade_cnh < $hoje;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $motorista->nome }}</strong>
                                    <div class="small text-body-secondary">{{ $motorista->identificador }}</div>
                                </td>
                                <td>{{ $motorista->unidade }}</td>
                                <td>
                                    {{ $motorista->numero_cnh }}
                                    <div class="small text-body-secondary">
                                        CNH {{ $motorista->categoria_cnh }} · Autorizadas: {{ $motorista->categorias_autorizadas }}
                                    </div>
                                </td>
                                <td>
                                    {{ $leituras->format($motorista->validade_cnh, 'date') }}
                                    @if ($vencida)
                                        <span class="badge text-bg-danger ms-1">Vencida</span>
                                    @endif
                                </td>
                                <td>
                                    <x-ui.status
                                        :valor="$motorista->ativo && $motorista->usuario_ativo ? 'ativo' : 'inativo'"
                                    />
                                </td>
                                <td>
                                    <a
                                        class="btn btn-outline-secondary btn-sm"
                                        href="{{ route('drivers.show', $motorista->usuario_id) }}"
                                        >Abrir</a
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-navigation.pagination :paginacao="$paginacao" />
        @endif
    </x-ui.panel>
</x-layouts.authenticated>
