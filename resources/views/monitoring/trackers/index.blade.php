<x-layouts.authenticated
    titulo="Rastreadores"
    :breadcrumbs="[['label' => 'Monitoramento', 'href' => Route::has('monitoring.index') && $acesso->level('rastreamento') > 0 ? route('monitoring.index') : null], ['label' => 'Rastreadores']]"
>
    <x-ui.title
        titulo="Rastreadores e instalações"
        subtitulo="Cadastre dispositivos de rastreamento e gerencie o vínculo ativo em cada veículo."
    >
        <x-slot:acoes>
            @if ($acesso->level('rastreamento') > 0 && Route::has('monitoring.index'))
                <a class="btn btn-outline-secondary" href="{{ route('monitoring.index') }}">Voltar ao monitoramento</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>

    @if ($podeGerenciar)
        <x-ui.panel titulo="Cadastrar rastreador">
            <form method="post" action="{{ route('trackers.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <x-forms.field nome="provedor" rotulo="Provedor" :obrigatorio="true" maxlength="100" />
                    </div>
                    <div class="col-md-3">
                        <x-forms.field
                            nome="identificador_externo"
                            rotulo="Identificador externo (IMEI / ID)"
                            :obrigatorio="true"
                            maxlength="100"
                        />
                    </div>
                    <div class="col-md-2">
                        <x-forms.field
                            nome="tipo"
                            rotulo="Tipo"
                            tipo="select"
                            :valor="'gps'"
                            :opcoes="['gps' => 'GPS', 'telematica' => 'Telemática', 'outro' => 'Outro']"
                            :obrigatorio="true"
                        />
                    </div>
                    <div class="col-md-2">
                        <x-forms.field nome="modelo" rotulo="Modelo" maxlength="100" />
                    </div>
                    <div class="col-md-2">
                        <x-forms.field
                            nome="ativo"
                            rotulo="Situação"
                            tipo="select"
                            :valor="'1'"
                            :opcoes="['1' => 'Ativo', '0' => 'Inativo']"
                            :obrigatorio="true"
                        />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Cadastrar rastreador</button>
            </form>
        </x-ui.panel>
    @endif

    <x-ui.panel titulo="Dispositivos cadastrados">
        <form method="get" action="{{ route('trackers.index') }}" class="filter-grid">
            <x-forms.field nome="q" rotulo="Buscar rastreador ou placa" :valor="$filtros['q'] ?? ''" maxlength="150" />
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-link" href="{{ route('trackers.index') }}">Limpar</a>
            </div>
        </form>

        @if ($rastreadores->isEmpty())
            <x-ui.empty
                titulo="Nenhum rastreador cadastrado"
                descricao="Cadastre um rastreador acima para vincular a um veículo."
            />
        @else
            <div class="table-responsive" tabindex="0" role="region" aria-label="Rastreadores cadastrados">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Provedor / ID</th>
                            <th scope="col">Tipo / Modelo</th>
                            <th scope="col">Situação</th>
                            <th scope="col">Veículo instalado</th>
                            @if ($podeGerenciar)
                                <th scope="col">Ações</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rastreadores as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->provedor }}</strong>
                                    <div class="small text-body-secondary">{{ $item->identificador_externo }}</div>
                                </td>
                                <td>{{ strtoupper($item->tipo) }}{{ $item->modelo ? ' · '.$item->modelo : '' }}</td>
                                <td><x-ui.status :valor="$item->ativo ? 'ativo' : 'inativo'" /></td>
                                <td>
                                    @if ($item->instalacao_id)
                                        <strong>{{ $item->placa }}</strong>
                                        · {{ $item->veiculo_nome }}
                                        <div class="small text-body-secondary">
                                            Desde {{ $leituras->format($item->instalado_em, 'datetime') }}
                                        </div>
                                    @else
                                        <span class="text-body-secondary">Não instalado</span>
                                    @endif
                                </td>
                                @if ($podeGerenciar)
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if ($item->instalacao_id)
                                                <form method="post" action="{{ route('trackers.remove', $item->id) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        Remover do veículo
                                                    </button>
                                                </form>
                                            @elseif ($item->ativo && ! empty($veiculosDisponiveis))
                                                <form
                                                    method="post"
                                                    action="{{ route('trackers.install', $item->id) }}"
                                                    class="d-flex gap-2 align-items-center"
                                                >
                                                    @csrf
                                                    <select
                                                        name="veiculo_id"
                                                        class="form-select form-select-sm"
                                                        aria-label="Veículo para instalar"
                                                        required
                                                    >
                                                        <option value="">Selecionar veículo…</option>
                                                        @foreach ($veiculosDisponiveis as $vid => $vlabel)
                                                            <option value="{{ $vid }}">{{ $vlabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                                        Instalar
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="post" action="{{ route('trackers.update', $item->id) }}">
                                                @csrf
                                                <input type="hidden" name="provedor" value="{{ $item->provedor }}" />
                                                <input
                                                    type="hidden"
                                                    name="identificador_externo"
                                                    value="{{ $item->identificador_externo }}"
                                                />
                                                <input type="hidden" name="tipo" value="{{ $item->tipo }}" />
                                                <input type="hidden" name="modelo" value="{{ $item->modelo ?? '' }}" />
                                                <input type="hidden" name="ativo" value="{{ $item->ativo ? 0 : 1 }}" />
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                    {{ $item->ativo ? 'Desativar' : 'Ativar' }}
                                                </button>
                                            </form>
                                            <details class="w-100">
                                                <summary class="btn btn-outline-secondary btn-sm">Editar</summary>
                                                <form
                                                    method="post"
                                                    action="{{ route('trackers.update', $item->id) }}"
                                                    class="row g-2 mt-1 p-2 border rounded"
                                                >
                                                    @csrf
                                                    <div class="col-md-4">
                                                        <input
                                                            type="text"
                                                            name="provedor"
                                                            value="{{ $item->provedor }}"
                                                            class="form-control form-control-sm"
                                                            maxlength="100"
                                                            aria-label="Provedor"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input
                                                            type="text"
                                                            name="identificador_externo"
                                                            value="{{ $item->identificador_externo }}"
                                                            class="form-control form-control-sm"
                                                            maxlength="100"
                                                            aria-label="Identificador externo"
                                                            required
                                                        />
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select
                                                            name="tipo"
                                                            class="form-select form-select-sm"
                                                            aria-label="Tipo"
                                                        >
                                                            <option value="gps" @selected ($item->tipo === 'gps')>GPS</option>
                                                            <option value="telematica" @selected ($item->tipo === 'telematica')>
                                                                Telemática
                                                            </option>
                                                            <option value="outro" @selected ($item->tipo === 'outro')>
                                                                Outro
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <input
                                                            type="text"
                                                            name="modelo"
                                                            value="{{ $item->modelo ?? '' }}"
                                                            class="form-control form-control-sm"
                                                            maxlength="100"
                                                            placeholder="Modelo"
                                                            aria-label="Modelo"
                                                        />
                                                    </div>
                                                    <div class="col-md-3">
                                                        <select
                                                            name="ativo"
                                                            class="form-select form-select-sm"
                                                            aria-label="Situação"
                                                        >
                                                            <option value="1" @selected ((bool) $item->ativo)>Ativo</option>
                                                            <option value="0" @selected (! (bool) $item->ativo)>
                                                                Inativo
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                                            Salvar
                                                        </button>
                                                    </div>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.panel>
</x-layouts.authenticated>
