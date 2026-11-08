@if ($acao === 'temporary')
    <x-ui.alert tom="warning">
        Confirme a identidade do titular antes de emitir e entregar o código. A emissão encerra as sessões do usuário e
        substitui a senha anterior.</x-ui.alert
    >
    <x-forms.password nome="senha_atual" rotulo="Sua senha de administrador" />
@elseif ($acao === 'revoke')
    <x-forms.field nome="vinculo_id" rotulo="Vínculo" tipo="select" :opcoes="$opcoesAdmin" :obrigatorio="true" />
    <x-forms.field nome="motivo" rotulo="Motivo da revogação" tipo="textarea" maxlength="500" :obrigatorio="true" />
    <x-forms.field
        nome="confirmar_revogacao"
        rotulo="Confirmo a revogação e o encerramento das sessões desse vínculo"
        tipo="select"
        :opcoes="['1' => 'Confirmo']"
        :obrigatorio="true"
    />
@elseif ($acao === 'links')
    <x-forms.field
        nome="perfil_id"
        rotulo="Perfil"
        tipo="select"
        :opcoes="$perfisDisponiveis ?? []"
        :obrigatorio="true"
    />
    <x-forms.field
        nome="unidade_id"
        rotulo="Unidade do vínculo"
        tipo="select"
        :opcoes="$unidadesDisponiveis ?? []"
        :valor="$registro->__unit ?? ''"
        :obrigatorio="true"
    />
    <div class="row">
        <div class="col-md-6">
            <x-forms.field
                nome="vigente_desde"
                rotulo="Vigência a partir de"
                tipo="datetime-local"
                :obrigatorio="true"
                data-date-start
            />
        </div>
        <div class="col-md-6">
            <x-forms.field nome="vigente_ate" rotulo="Vigência até" tipo="datetime-local" data-date-end />
        </div>
    </div>
    <x-ui.alert tom="warning">
        Concessões precisam respeitar permissões delegáveis. O último administrador permanente deve permanecer
        ativo.</x-ui.alert
    >
@elseif ($acao === 'create')
    <x-forms.field
        nome="unidade_id"
        rotulo="Unidade"
        tipo="select"
        :opcoes="$unidadesDisponiveis ?? []"
        :obrigatorio="true"
    />
    <x-forms.field
        nome="identificador"
        rotulo="CPF"
        :valor="old('identificador')"
        :obrigatorio="true"
        inputmode="numeric"
        maxlength="14"
    />
    <x-forms.field nome="nome" rotulo="Nome completo" :valor="old('nome')" :obrigatorio="true" maxlength="150" />
    <x-forms.field nome="email" rotulo="E-mail de contato" tipo="email" maxlength="254" />
    <p class="form-text">O código temporário será exibido após o cadastro para entrega ao titular.</p>
@else
    <x-forms.field
        nome="unidade_id"
        rotulo="Unidade"
        tipo="select"
        :valor="$registro->__unit ?? ''"
        :opcoes="$unidadesDisponiveis ?? []"
        :obrigatorio="true"
    />
    <x-forms.field
        nome="identificador"
        rotulo="CPF ou identificador existente"
        :valor="$registro->identificador ?? ''"
        :obrigatorio="true"
        maxlength="100"
    />
    <x-forms.field
        nome="nome"
        rotulo="Nome completo"
        :valor="$registro->nome ?? ''"
        :obrigatorio="true"
        maxlength="150"
    />
    <x-forms.field nome="email" rotulo="E-mail" tipo="email" :valor="$registro->email ?? ''" maxlength="254" />
    <x-forms.field nome="telefone" rotulo="Telefone" :valor="$registro->telefone ?? ''" maxlength="30" />
    <x-forms.field
        nome="ativo"
        rotulo="Situação"
        tipo="select"
        :valor="$registro->situacao"
        :obrigatorio="true"
        :opcoes="['1'=>'Ativo','0'=>'Inativo']"
    />
@endif
