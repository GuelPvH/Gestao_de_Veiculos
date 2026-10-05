<x-forms.field nome="nome" rotulo="Nome do perfil" :valor="$acao==='duplicate' ? '' : ($registro->nome ?? '')" :obrigatorio="true" maxlength="100" />
<x-forms.field nome="codigo" rotulo="Código do perfil" :obrigatorio="true" maxlength="40" pattern="[a-z][a-z0-9_]*" />
<x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$registro->descricao ?? ''" maxlength="1000" />
<x-ui.alert tom="info">A duplicação permite revisar a matriz de origem. A concessão final depende dos limites delegáveis do vínculo selecionado.</x-ui.alert>
<x-tables.permission-matrix :permissoes="$permissoes ?? []" />
