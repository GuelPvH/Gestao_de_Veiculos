@if (in_array($acao, ['grant', 'revoke'], true))
    <x-forms.field nome="permissao_id" rotulo="Permissão" tipo="select" :opcoes="$opcoesAdmin" :obrigatorio="true" />
    @if ($acao === 'grant')
        @if ($rotaSelecionada !== null)
            <input type="hidden" name="rota_id" value="{{ $rotaSelecionada }}" />
        @endif
        <x-forms.field
            nome="delegavel"
            rotulo="Pode ser delegada"
            tipo="select"
            :opcoes="['0' => 'Não', '1' => 'Sim']"
            :valor="0"
            :obrigatorio="true"
        />
    @else
        <x-forms.field
            nome="confirmar_revogacao"
            rotulo="Confirmo a revogação da permissão"
            tipo="select"
            :opcoes="['1' => 'Confirmo']"
            :obrigatorio="true"
        />
    @endif
@elseif ($acao === 'edit')
    <x-forms.field
        nome="nome"
        rotulo="Nome do perfil"
        :valor="$registro->nome ?? ''"
        :obrigatorio="true"
        maxlength="100"
    />
    <x-forms.field
        nome="descricao"
        rotulo="Descrição"
        tipo="textarea"
        :valor="$registro->descricao ?? ''"
        maxlength="3000"
    />
    <x-forms.field
        nome="ativo"
        rotulo="Situação"
        tipo="select"
        :valor="$registro->situacao"
        :obrigatorio="true"
        :opcoes="['1'=>'Ativo','0'=>'Inativo']"
    />
@else
    <x-forms.field
        nome="codigo"
        rotulo="Código do perfil"
        :valor="old('codigo')"
        :obrigatorio="true"
        maxlength="60"
        pattern="[a-z][a-z0-9_]*"
    />
    <x-forms.field
        nome="nome"
        rotulo="Nome do perfil"
        :valor="$acao === 'duplicate' ? '' : ($registro->nome ?? '')"
        :obrigatorio="true"
        maxlength="100"
    />
    @if ($acao === 'create')
        <x-forms.field
            nome="descricao"
            rotulo="Descrição"
            tipo="textarea"
            :valor="$registro->descricao ?? ''"
            maxlength="3000"
        />
    @endif
    @if ($acao === 'duplicate')
        <x-ui.alert tom="info">
            A duplicação permite clonar permissões delegáveis do perfil original para o novo perfil.</x-ui.alert
        >
        <x-tables.permission-matrix :permissoes="$permissoes ?? []" />
    @endif
@endif
