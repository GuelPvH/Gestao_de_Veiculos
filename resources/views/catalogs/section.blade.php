<x-layouts.authenticated
    :titulo="$meta['title']"
    :breadcrumbs="$podeVerHub ? [['label' => 'Cadastros auxiliares', 'href' => route('catalogs.index')], ['label' => $meta['title']]] : [['label' => $meta['title']]]"
>
    <x-ui.title :titulo="$meta['title']" :subtitulo="$meta['description']">
        <x-slot:acoes>
            @if ($podeVerHub)
                <a class="btn btn-outline-secondary" href="{{ route('catalogs.index') }}">Todos os cadastros</a>
            @endif
        </x-slot:acoes>
    </x-ui.title>

    @if ($podeEditar)
        <x-ui.panel titulo="Novo registro">
            <form method="post" action="{{ route('catalogs.store', $tipo) }}">
                @csrf
                @if ($tipo === 'unidades')
                    <div class="row">
                        <div class="col-md-3">
                            <x-forms.field
                                nome="codigo"
                                rotulo="Código"
                                :obrigatorio="true"
                                maxlength="30"
                                placeholder="EX.: DIR-OP"
                            />
                        </div>
                        <div class="col-md-5">
                            <x-forms.field nome="nome" rotulo="Nome da unidade" :obrigatorio="true" maxlength="150" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.field
                                nome="unidade_superior_id"
                                rotulo="Unidade superior (opcional)"
                                tipo="select"
                                :opcoes="$unidadesOpcoes"
                                placeholder="Nenhuma"
                            />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-5">
                            <x-forms.field nome="endereco" rotulo="Endereço" maxlength="255" />
                        </div>
                        <div class="col-md-3">
                            <x-forms.field nome="cidade" rotulo="Cidade" maxlength="100" />
                        </div>
                        <div class="col-md-2">
                            <x-forms.field nome="uf" rotulo="UF" maxlength="2" placeholder="RO" />
                        </div>
                        <div class="col-md-2">
                            <x-forms.field
                                nome="ativa"
                                rotulo="Situação"
                                tipo="select"
                                :valor="'1'"
                                :opcoes="['1' => 'Ativa', '0' => 'Inativa']"
                                :obrigatorio="true"
                            />
                        </div>
                    </div>
                @elseif ($tipo === 'categorias-veiculo')
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.field nome="nome" rotulo="Nome da categoria" :obrigatorio="true" maxlength="60" />
                        </div>
                        <div class="col-md-3">
                            <x-forms.field
                                nome="categoria_cnh_requerida"
                                rotulo="CNH mínima"
                                tipo="select"
                                :valor="'B'"
                                :opcoes="['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E']"
                                :obrigatorio="true"
                            />
                        </div>
                        <div class="col-md-3">
                            <x-forms.field
                                nome="ativa"
                                rotulo="Situação"
                                tipo="select"
                                :valor="'1'"
                                :opcoes="['1' => 'Ativa', '0' => 'Inativa']"
                                :obrigatorio="true"
                            />
                        </div>
                    </div>
                @elseif ($tipo === 'categorias-despesa')
                    <div class="row">
                        <div class="col-md-4">
                            <x-forms.field
                                nome="codigo"
                                rotulo="Código"
                                :obrigatorio="true"
                                maxlength="40"
                                placeholder="ex.: pedagio"
                            />
                        </div>
                        <div class="col-md-5">
                            <x-forms.field nome="nome" rotulo="Nome" :obrigatorio="true" maxlength="80" />
                        </div>
                        <div class="col-md-3">
                            <x-forms.field
                                nome="ativa"
                                rotulo="Situação"
                                tipo="select"
                                :valor="'1'"
                                :opcoes="['1' => 'Ativa', '0' => 'Inativa']"
                                :obrigatorio="true"
                            />
                        </div>
                    </div>
                @elseif ($tipo === 'categorias-chamado')
                    <div class="row">
                        <div class="col-md-8">
                            <x-forms.field nome="nome" rotulo="Nome da categoria" :obrigatorio="true" maxlength="80" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.field
                                nome="ativa"
                                rotulo="Situação"
                                tipo="select"
                                :valor="'1'"
                                :opcoes="['1' => 'Ativa', '0' => 'Inativa']"
                                :obrigatorio="true"
                            />
                        </div>
                    </div>
                @elseif ($tipo === 'checklist')
                    <div class="row">
                        <div class="col-md-3">
                            <x-forms.field
                                nome="codigo"
                                rotulo="Código"
                                :obrigatorio="true"
                                maxlength="40"
                                placeholder="ex.: iluminacao"
                            />
                        </div>
                        <div class="col-md-4">
                            <x-forms.field
                                nome="descricao"
                                rotulo="Descrição do item"
                                :obrigatorio="true"
                                maxlength="150"
                            />
                        </div>
                        <div class="col-md-2">
                            <x-forms.field
                                nome="obrigatorio"
                                rotulo="Obrigatório"
                                tipo="select"
                                :valor="'1'"
                                :opcoes="['1' => 'Sim', '0' => 'Não']"
                                :obrigatorio="true"
                            />
                        </div>
                        <div class="col-md-1">
                            <x-forms.field
                                nome="ordem"
                                rotulo="Ordem"
                                tipo="number"
                                :valor="'40'"
                                min="0"
                                max="65535"
                                :obrigatorio="true"
                            />
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
                @elseif ($tipo === 'fornecedores')
                    <div class="row">
                        <div class="col-md-5">
                            <x-forms.field
                                nome="nome"
                                rotulo="Nome / Razão social"
                                :obrigatorio="true"
                                maxlength="150"
                            />
                        </div>
                        <div class="col-md-3">
                            <x-forms.field nome="cpf_cnpj" rotulo="CPF ou CNPJ" maxlength="18" />
                        </div>
                        <div class="col-md-4">
                            <x-forms.field nome="email" rotulo="E-mail" tipo="email" maxlength="254" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-forms.field nome="telefone" rotulo="Telefone" maxlength="30" />
                        </div>
                        <div class="col-md-6">
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
                @endif
                <button type="submit" class="btn btn-primary">Cadastrar</button>
            </form>
        </x-ui.panel>
    @endif

    <x-ui.panel titulo="Registros cadastrados">
        <form method="get" class="filter-grid">
            <x-forms.field nome="q" rotulo="Buscar" :valor="$filtros['q'] ?? ''" maxlength="150" />
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a class="btn btn-link" href="{{ url()->current() }}">Limpar</a>
            </div>
        </form>

        @if ($itens->isEmpty())
            <x-ui.empty titulo="Nenhum registro encontrado" descricao="Cadastre um novo item acima ou limpe a busca." />
        @else
            <div class="table-responsive" tabindex="0" role="region" aria-label="Registros do cadastro auxiliar">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            @if ($tipo === 'unidades')
                                <th scope="col">Código</th>
                                <th scope="col">Nome / Superior</th>
                                <th scope="col">Localização</th>
                                <th scope="col">Situação</th>
                            @elseif ($tipo === 'categorias-veiculo')
                                <th scope="col">Nome</th>
                                <th scope="col">CNH mínima</th>
                                <th scope="col">Situação</th>
                            @elseif ($tipo === 'categorias-despesa')
                                <th scope="col">Código</th>
                                <th scope="col">Nome</th>
                                <th scope="col">Situação</th>
                            @elseif ($tipo === 'categorias-chamado')
                                <th scope="col">Nome</th>
                                <th scope="col">Situação</th>
                            @elseif ($tipo === 'checklist')
                                <th scope="col">Ordem / Código</th>
                                <th scope="col">Descrição</th>
                                <th scope="col">Obrigatório</th>
                                <th scope="col">Situação</th>
                            @elseif ($tipo === 'fornecedores')
                                <th scope="col">Fornecedor</th>
                                <th scope="col">CPF/CNPJ</th>
                                <th scope="col">Contato</th>
                                <th scope="col">Situação</th>
                            @endif
                            @if ($podeEditar)
                                <th scope="col">Edição rápida</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itens as $item)
                            @php
                                $estaAtivo = (bool) ($item->ativa ?? $item->ativo ?? false);
                            @endphp
                            <tr>
                                @if ($tipo === 'unidades')
                                    <td><strong>{{ $item->codigo }}</strong></td>
                                    <td>
                                        {{ $item->nome }}
                                        @if ($item->unidade_superior_nome)
                                            <div class="small text-body-secondary">
                                                Superior: {{ $item->unidade_superior_nome }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ trim(($item->cidade ?? '').($item->uf ? '/'.$item->uf : '')) ?: '—' }}</td>
                                    <td><x-ui.status :valor="$estaAtivo ? 'ativo' : 'inativo'" /></td>
                                @elseif ($tipo === 'categorias-veiculo')
                                    <td><strong>{{ $item->nome }}</strong></td>
                                    <td>{{ $item->categoria_cnh_requerida }}</td>
                                    <td><x-ui.status :valor="$estaAtivo ? 'ativo' : 'inativo'" /></td>
                                @elseif ($tipo === 'categorias-despesa')
                                    <td><code>{{ $item->codigo }}</code></td>
                                    <td><strong>{{ $item->nome }}</strong></td>
                                    <td><x-ui.status :valor="$estaAtivo ? 'ativo' : 'inativo'" /></td>
                                @elseif ($tipo === 'categorias-chamado')
                                    <td><strong>{{ $item->nome }}</strong></td>
                                    <td><x-ui.status :valor="$estaAtivo ? 'ativo' : 'inativo'" /></td>
                                @elseif ($tipo === 'checklist')
                                    <td>{{ $item->ordem }} · <code>{{ $item->codigo }}</code></td>
                                    <td><strong>{{ $item->descricao }}</strong></td>
                                    <td>{{ $item->obrigatorio ? 'Sim' : 'Não' }}</td>
                                    <td><x-ui.status :valor="$estaAtivo ? 'ativo' : 'inativo'" /></td>
                                @elseif ($tipo === 'fornecedores')
                                    <td><strong>{{ $item->nome }}</strong></td>
                                    <td>{{ $item->cpf_cnpj ?? '—' }}</td>
                                    <td>{{ $item->email ?? '—' }}{{ $item->telefone ? ' · '.$item->telefone : '' }}</td>
                                    <td><x-ui.status :valor="$estaAtivo ? 'ativo' : 'inativo'" /></td>
                                @endif
                                @if ($podeEditar)
                                    <td>
                                        <form
                                            method="post"
                                            action="{{ route('catalogs.update', ['tipo' => $tipo, 'registro' => $item->id]) }}"
                                            class="d-flex flex-wrap gap-2 align-items-center"
                                        >
                                            @csrf
                                            @if ($tipo === 'unidades')
                                                <input
                                                    type="text"
                                                    name="codigo"
                                                    value="{{ $item->codigo }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 90px"
                                                    required
                                                />
                                                <input
                                                    type="text"
                                                    name="nome"
                                                    value="{{ $item->nome }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 180px"
                                                    required
                                                />
                                                <input
                                                    type="hidden"
                                                    name="unidade_superior_id"
                                                    value="{{ $item->unidade_superior_id }}"
                                                />
                                                <input type="hidden" name="endereco" value="{{ $item->endereco }}" />
                                                <input
                                                    type="text"
                                                    name="cidade"
                                                    value="{{ $item->cidade }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 120px"
                                                    placeholder="Cidade"
                                                />
                                                <input
                                                    type="text"
                                                    name="uf"
                                                    value="{{ $item->uf }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 55px"
                                                    maxlength="2"
                                                    placeholder="UF"
                                                />
                                                <select
                                                    name="ativa"
                                                    class="form-select form-select-sm"
                                                    style="width: 100px"
                                                >
                                                    <option value="1" @selected ($estaAtivo)>Ativa</option>
                                                    <option value="0" @selected (! $estaAtivo)>Inativa</option>
                                                </select>
                                            @elseif ($tipo === 'categorias-veiculo')
                                                <input
                                                    type="text"
                                                    name="nome"
                                                    value="{{ $item->nome }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 160px"
                                                    required
                                                />
                                                <select
                                                    name="categoria_cnh_requerida"
                                                    class="form-select form-select-sm"
                                                    style="width: 75px"
                                                >
                                                    @foreach (['A', 'B', 'C', 'D', 'E'] as $cat)
                                                        <option
                                                            value="{{ $cat }}"
                                                            @selected ($item->categoria_cnh_requerida === $cat)
                                                        >
                                                            {{ $cat }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <select
                                                    name="ativa"
                                                    class="form-select form-select-sm"
                                                    style="width: 100px"
                                                >
                                                    <option value="1" @selected ($estaAtivo)>Ativa</option>
                                                    <option value="0" @selected (! $estaAtivo)>Inativa</option>
                                                </select>
                                            @elseif ($tipo === 'categorias-despesa')
                                                <input
                                                    type="text"
                                                    name="codigo"
                                                    value="{{ $item->codigo }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 120px"
                                                    required
                                                />
                                                <input
                                                    type="text"
                                                    name="nome"
                                                    value="{{ $item->nome }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 160px"
                                                    required
                                                />
                                                <select
                                                    name="ativa"
                                                    class="form-select form-select-sm"
                                                    style="width: 100px"
                                                >
                                                    <option value="1" @selected ($estaAtivo)>Ativa</option>
                                                    <option value="0" @selected (! $estaAtivo)>Inativa</option>
                                                </select>
                                            @elseif ($tipo === 'categorias-chamado')
                                                <input
                                                    type="text"
                                                    name="nome"
                                                    value="{{ $item->nome }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 200px"
                                                    required
                                                />
                                                <select
                                                    name="ativa"
                                                    class="form-select form-select-sm"
                                                    style="width: 100px"
                                                >
                                                    <option value="1" @selected ($estaAtivo)>Ativa</option>
                                                    <option value="0" @selected (! $estaAtivo)>Inativa</option>
                                                </select>
                                            @elseif ($tipo === 'checklist')
                                                <input
                                                    type="text"
                                                    name="codigo"
                                                    value="{{ $item->codigo }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 110px"
                                                    required
                                                />
                                                <input
                                                    type="text"
                                                    name="descricao"
                                                    value="{{ $item->descricao }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 170px"
                                                    required
                                                />
                                                <input
                                                    type="number"
                                                    name="ordem"
                                                    value="{{ $item->ordem }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 75px"
                                                    min="0"
                                                    max="65535"
                                                    required
                                                />
                                                <select
                                                    name="obrigatorio"
                                                    class="form-select form-select-sm"
                                                    style="width: 110px"
                                                >
                                                    <option value="1" @selected ((bool) $item->obrigatorio)>
                                                        Obrigatório
                                                    </option>
                                                    <option value="0" @selected (! (bool) $item->obrigatorio)>
                                                        Opcional
                                                    </option>
                                                </select>
                                                <select
                                                    name="ativo"
                                                    class="form-select form-select-sm"
                                                    style="width: 95px"
                                                >
                                                    <option value="1" @selected ($estaAtivo)>Ativo</option>
                                                    <option value="0" @selected (! $estaAtivo)>Inativo</option>
                                                </select>
                                            @elseif ($tipo === 'fornecedores')
                                                <input
                                                    type="text"
                                                    name="nome"
                                                    value="{{ $item->nome }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 160px"
                                                    required
                                                />
                                                <input
                                                    type="text"
                                                    name="cpf_cnpj"
                                                    value="{{ $item->cpf_cnpj }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 130px"
                                                    placeholder="CPF/CNPJ"
                                                />
                                                <input
                                                    type="email"
                                                    name="email"
                                                    value="{{ $item->email }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 150px"
                                                    placeholder="E-mail"
                                                />
                                                <input
                                                    type="text"
                                                    name="telefone"
                                                    value="{{ $item->telefone }}"
                                                    class="form-control form-control-sm"
                                                    style="width: 120px"
                                                    placeholder="Telefone"
                                                />
                                                <select
                                                    name="ativo"
                                                    class="form-select form-select-sm"
                                                    style="width: 95px"
                                                >
                                                    <option value="1" @selected ($estaAtivo)>Ativo</option>
                                                    <option value="0" @selected (! $estaAtivo)>Inativo</option>
                                                </select>
                                            @endif
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Salvar</button>
                                        </form>
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
