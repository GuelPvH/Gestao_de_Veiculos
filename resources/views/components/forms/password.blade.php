@props(['nome' => 'senha', 'rotulo' => 'Senha', 'autocomplete' => 'current-password'])
<div class="password-field">
    <x-forms.field :nome="$nome" :rotulo="$rotulo" tipo="password" :obrigatorio="true" :autocomplete="$autocomplete" maxlength="256" />
    <button type="button" class="btn btn-outline-secondary password-toggle" data-password-toggle="field-{{ $nome }}" aria-label="Mostrar {{ mb_strtolower($rotulo) }}">Mostrar</button>
</div>
