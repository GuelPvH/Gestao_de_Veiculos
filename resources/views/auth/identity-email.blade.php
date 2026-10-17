<p>Há uma atualização de acesso no Frota · PF.</p>
@if ($url)
    <p><a href="{{ $url }}">{{
        match ($finalidade) {
            'ativacao' => 'Ativar acesso e definir senha',
            'email' => 'Confirmar meu e-mail',
            default => 'Redefinir senha',
        }
    }}</a></p>
    <p>Este link é individual, tem validade limitada e pode ser utilizado uma única vez. Não o compartilhe.</p>
@else
    <p>A segurança da sua conta foi alterada. Se não reconhece a ação, contate a administração pelos canais institucionais.</p>
@endif
<p>O recebimento desta mensagem não confirma automaticamente o endereço nem libera o acesso.</p>
