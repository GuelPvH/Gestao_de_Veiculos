<x-layouts.authenticated
    :titulo="$titulo"
    :breadcrumbs="[['label' => 'Motoristas', 'href' => route('drivers.index')], ['label' => $titulo]]"
>
    <x-ui.title
        :titulo="$titulo"
        subtitulo="Informe os dados da CNH e as categorias autorizadas para condução de veículos da frota."
    />
    <x-ui.panel>
        <form method="post" action="{{ $formAction }}" enctype="multipart/form-data">
            @csrf
            @if ($acao === 'create')
                @if (empty($usuariosDisponiveis))
                    <x-ui.alert tom="warning">
                        Todos os usuários ativos no alcance já possuem cadastro de motorista ou não há usuários
                        disponíveis.
                    </x-ui.alert>
                @endif
                <x-forms.field
                    nome="usuario_id"
                    rotulo="Usuário"
                    tipo="select"
                    :valor="old('usuario_id', $usuarioSelecionado ?? '')"
                    :opcoes="$usuariosDisponiveis"
                    :obrigatorio="true"
                />
            @else
                <dl class="detail-grid mb-3">
                    <div>
                        <dt>Usuário</dt>
                        <dd>{{ $motorista->nome }} ({{ $motorista->identificador }})</dd>
                    </div>
                    <div>
                        <dt>Unidade</dt>
                        <dd>{{ $motorista->unidade }}</dd>
                    </div>
                </dl>
            @endif
            <div class="row">
                <div class="col-md-4">
                    <x-forms.field
                        nome="numero_cnh"
                        rotulo="Número da CNH"
                        :valor="old('numero_cnh', $motorista->numero_cnh ?? '')"
                        :obrigatorio="true"
                        maxlength="20"
                    />
                </div>
                <div class="col-md-4">
                    <x-forms.field
                        nome="categoria_cnh"
                        rotulo="Categoria impressa na CNH"
                        :valor="old('categoria_cnh', $motorista->categoria_cnh ?? 'B')"
                        :obrigatorio="true"
                        maxlength="10"
                        placeholder="Ex.: B, AB, D, AE"
                    />
                </div>
                <div class="col-md-4">
                    <x-forms.field
                        nome="categorias_autorizadas"
                        rotulo="Categorias autorizadas (A-E)"
                        :valor="old('categorias_autorizadas', $motorista->categorias_autorizadas ?? 'B')"
                        :obrigatorio="true"
                        maxlength="5"
                        placeholder="Ex.: B ou AB ou BCD"
                        nota="Informe todas as letras autorizadas (ex.: para categoria D, informe BCD)."
                    />
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <x-forms.field
                        nome="validade_cnh"
                        rotulo="Validade da CNH"
                        tipo="date"
                        :valor="old('validade_cnh', $motorista->validade_cnh ?? '')"
                        :obrigatorio="true"
                    />
                </div>
                <div class="col-md-6">
                    <x-forms.field
                        nome="ativo"
                        rotulo="Situação"
                        tipo="select"
                        :valor="old('ativo', isset($motorista) ? (string) $motorista->ativo : '1')"
                        :opcoes="['1' => 'Ativo', '0' => 'Inativo']"
                        :obrigatorio="true"
                    />
                </div>
            </div>
            <x-forms.field
                nome="cnh_arquivo"
                rotulo="Cópia da CNH (opcional — PDF, PNG ou JPG, até 10 MB)"
                tipo="file"
                accept="application/pdf,image/png,image/jpeg"
            />
            <div class="form-actions">
                <a
                    class="btn btn-outline-secondary"
                    href="{{ $motorista ? route('drivers.show', $motorista->usuario_id) : route('drivers.index') }}"
                    >Cancelar</a
                >
                <button type="submit" class="btn btn-primary">Salvar motorista</button>
            </div>
        </form>
    </x-ui.panel>
</x-layouts.authenticated>
