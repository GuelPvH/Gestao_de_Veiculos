@if($acao === 'edit')
    <x-forms.field nome="nome" rotulo="Nome do perfil" :valor="$registro->nome ?? ''" :obrigatorio="true" maxlength="100" />
    <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$registro->descricao ?? ''" maxlength="3000" />
    <x-forms.field nome="ativo" rotulo="Situação" tipo="select" :valor="$registro->ativo ?? 1" :obrigatorio="true" :opcoes="['1'=>'Ativo','0'=>'Inativo']" />
@else
    <x-forms.field nome="codigo" rotulo="Código do perfil" :valor="old('codigo')" :obrigatorio="true" maxlength="60" pattern="[a-z][a-z0-9_]*" />
    <x-forms.field nome="nome" rotulo="Nome do perfil" :valor="$acao === 'duplicate' ? '' : ($registro->nome ?? '')" :obrigatorio="true" maxlength="100" />
    <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$registro->descricao ?? ''" maxlength="3000" />
    @if($acao === 'duplicate')
        <x-ui.alert tom="info">A duplicação permite clonar permissões delegáveis do perfil original para o novo perfil.</x-ui.alert>
        <x-tables.permission-matrix :permissoes="$permissoes ?? []" />
    @endif
@endif

