@if($acao === 'links')
    <x-forms.field nome="perfil_id" rotulo="Perfil" tipo="select" :opcoes="$perfisDisponiveis ?? []" :obrigatorio="true" />
    <x-forms.field nome="unidade_id" rotulo="Unidade do vínculo" tipo="select" :opcoes="$unidadesDisponiveis ?? []" :valor="$registro->unidade_id ?? ''" :obrigatorio="true" />
    <div class="row"><div class="col-md-6"><x-forms.field nome="vigente_desde" rotulo="Vigência a partir de" tipo="datetime-local" :obrigatorio="true" data-date-start /></div><div class="col-md-6"><x-forms.field nome="vigente_ate" rotulo="Vigência até" tipo="datetime-local" data-date-end /></div></div>
    <x-ui.alert tom="warning">Concessões precisam respeitar permissões delegáveis. O último administrador permanente deve permanecer ativo.</x-ui.alert>
@elseif($acao === 'create')
    <x-forms.field nome="unidade_id" rotulo="Unidade" tipo="select" :opcoes="$unidadesDisponiveis ?? []" :obrigatorio="true" />
    <x-forms.field nome="identificador" rotulo="Identificador institucional" :valor="old('identificador')" :obrigatorio="true" maxlength="100" />
    <x-forms.field nome="nome" rotulo="Nome completo" :valor="old('nome')" :obrigatorio="true" maxlength="150" />
    <x-forms.field nome="email" rotulo="E-mail" tipo="email" :valor="old('email')" maxlength="254" />
    <x-forms.field nome="senha_temporaria" rotulo="Senha temporária inicial" tipo="password" :obrigatorio="true" nota="Mínimo 12 caracteres, com maiúsculas, minúsculas, números e símbolos." />
    <x-forms.field nome="senha_temporaria_confirmation" rotulo="Confirmar senha temporária" tipo="password" :obrigatorio="true" />
@else
    <x-forms.field nome="unidade_id" rotulo="Unidade" tipo="select" :valor="$registro->unidade_id ?? ''" :opcoes="$unidadesDisponiveis ?? []" :obrigatorio="true" />
    <x-forms.field nome="identificador" rotulo="Identificador institucional" :valor="$registro->identificador ?? ''" :obrigatorio="true" maxlength="100" />
    <x-forms.field nome="nome" rotulo="Nome completo" :valor="$registro->nome ?? ''" :obrigatorio="true" maxlength="150" />
    <x-forms.field nome="email" rotulo="E-mail" tipo="email" :valor="$registro->email ?? ''" maxlength="254" />
    <x-forms.field nome="telefone" rotulo="Telefone" :valor="$registro->telefone ?? ''" maxlength="30" />
    <x-forms.field nome="ativo" rotulo="Situação" tipo="select" :valor="$registro->ativo ?? 1" :obrigatorio="true" :opcoes="['1'=>'Ativo','0'=>'Inativo']" />
@endif

