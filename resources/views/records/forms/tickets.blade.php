@if($acao==='create')
    <x-forms.field nome="categoria" rotulo="Categoria" tipo="select" :obrigatorio="true" :opcoes="$categoriasChamado ?? []" />
    <x-forms.field nome="assunto" rotulo="Assunto" :obrigatorio="true" maxlength="200" />
    <x-forms.field nome="descricao" rotulo="Descrição" tipo="textarea" :obrigatorio="true" maxlength="5000" />
    <x-forms.field nome="arquivo" rotulo="Anexo" tipo="file" accept="application/pdf,image/png,image/jpeg" />
@else<x-forms.field nome="mensagem" rotulo="Mensagem" tipo="textarea" :obrigatorio="true" maxlength="5000" />@endif
@if($acao==='assign')<x-forms.field nome="responsavel" rotulo="Responsável pelo atendimento" :obrigatorio="true" maxlength="150" />@endif
