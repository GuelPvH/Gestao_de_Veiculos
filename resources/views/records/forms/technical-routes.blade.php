<x-forms.field nome="nome" rotulo="Nome da rota" :valor="$registro->nome ?? ''" :obrigatorio="true" maxlength="120" />
<x-forms.field nome="caminho" rotulo="Caminho" :valor="$registro->caminho ?? ''" :obrigatorio="true" maxlength="255" />
<x-forms.field nome="metodo_http" rotulo="Método HTTP" tipo="select" :obrigatorio="true" :opcoes="['GET'=>'GET','POST'=>'POST','PUT'=>'PUT','PATCH'=>'PATCH','DELETE'=>'DELETE']" />
<x-forms.field nome="ativa" rotulo="Situação da rota" tipo="select" :obrigatorio="true" :opcoes="['1'=>'Ativa','0'=>'Desativada']" />
<x-ui.alert tom="info">O cadastro descreve caminhos já implementados no sistema. Ele não cria nem executa código.</x-ui.alert>
