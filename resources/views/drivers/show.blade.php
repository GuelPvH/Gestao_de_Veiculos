<x-layouts.authenticated
    :titulo="$motorista->nome.' · Motorista'"
    :breadcrumbs="[['label' => 'Motoristas', 'href' => route('drivers.index')], ['label' => $motorista->nome]]"
>
    <x-ui.title
        :titulo="$motorista->nome.' · Motorista'"
        subtitulo="Consulte os dados da habilitação e o documento privado da CNH."
    >
        <x-slot:acoes>
            <a class="btn btn-outline-secondary" href="{{ route('drivers.index') }}">Voltar à lista</a>
            @if ($podeGerenciar)
                <a class="btn btn-primary" href="{{ route('drivers.edit', $motorista->usuario_id) }}"
                    >Editar motorista</a
                >
            @endif
        </x-slot:acoes>
    </x-ui.title>

    <div class="dashboard-grid">
        <x-ui.panel titulo="Dados do condutor">
            <dl class="detail-grid mb-0">
                <div>
                    <dt>Nome</dt>
                    <dd>{{ $motorista->nome }}</dd>
                </div>
                <div>
                    <dt>Identificador</dt>
                    <dd>{{ $motorista->identificador }}</dd>
                </div>
                <div>
                    <dt>Unidade</dt>
                    <dd>{{ $motorista->unidade }}</dd>
                </div>
                <div>
                    <dt>Contato</dt>
                    <dd>{{ $motorista->email ?? '—' }}{{ $motorista->telefone ? ' · '.$motorista->telefone : '' }}</dd>
                </div>
                <div>
                    <dt>Número da CNH</dt>
                    <dd>{{ $motorista->numero_cnh }}</dd>
                </div>
                <div>
                    <dt>Categoria CNH</dt>
                    <dd>{{ $motorista->categoria_cnh }}</dd>
                </div>
                <div>
                    <dt>Categorias autorizadas</dt>
                    <dd>{{ $motorista->categorias_autorizadas }}</dd>
                </div>
                <div>
                    <dt>Validade da CNH</dt>
                    <dd>{{ $leituras->format($motorista->validade_cnh, 'date') }}</dd>
                </div>
                <div>
                    <dt>Situação</dt>
                    <dd>
                        <x-ui.status :valor="$motorista->ativo && $motorista->usuario_ativo ? 'ativo' : 'inativo'" />
                    </dd>
                </div>
            </dl>
        </x-ui.panel>

        <x-ui.panel titulo="Documento da CNH">
            @if ($motorista->cnh_arquivo_id && $motorista->cnh_arquivo_situacao === 'disponivel')
                <p class="mb-2">{{ $motorista->cnh_arquivo_nome }}</p>
                <a class="btn btn-outline-secondary" href="{{ route('drivers.cnh', $motorista->usuario_id) }}"
                    >Baixar CNH</a
                >
            @else
                <p class="text-body-secondary mb-0">Nenhum documento de CNH anexado.</p>
            @endif
        </x-ui.panel>
    </div>
</x-layouts.authenticated>
