<x-forms.field nome="nome" rotulo="Nome da rota" :valor="$registro->nome ?? ''" :obrigatorio="true" maxlength="120" />
<x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :valor="$registro->descricao ?? ''" maxlength="3000" />
<x-forms.field nome="caminho" rotulo="Caminho" :valor="$registro->caminho ?? ''" :readonly="true" />
<x-forms.field nome="metodo_http" rotulo="Método HTTP" :valor="$registro->metodo_http ?? ''" :readonly="true" />
<x-forms.field nome="ativa" rotulo="Situação da rota" tipo="select" :valor="$registro->ativa ?? 1" :obrigatorio="true" :opcoes="['1'=>'Ativa','0'=>'Desativada']" />
<x-forms.field nome="visivel_menu" rotulo="Visível no menu" tipo="select" :valor="$registro->visivel_menu ?? 1" :obrigatorio="true" :opcoes="['1'=>'Sim','0'=>'Não']" />
<x-forms.field nome="ordem" rotulo="Ordem de exibição" tipo="number" :valor="$registro->ordem ?? 0" :obrigatorio="true" min="0" max="65535" step="1" />
<x-ui.alert tom="info">O cadastro descreve caminhos já implementados no sistema. Ele não cria nem executa código.</x-ui.alert>

