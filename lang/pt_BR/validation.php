<?php

return [
    'required' => 'Informe :attribute.', 'string' => ':attribute deve ser um texto.', 'integer' => ':attribute deve ser um número inteiro.',
    'email' => 'Informe um e-mail válido.', 'confirmed' => 'A confirmação de :attribute não confere.', 'in' => 'O valor de :attribute não é válido.',
    'max' => ['string' => ':attribute deve ter no máximo :max caracteres.', 'numeric' => ':attribute deve ser no máximo :max.'],
    'min' => ['string' => ':attribute deve ter pelo menos :min caracteres.', 'numeric' => ':attribute deve ser pelo menos :min.'],
    'password' => ['letters' => 'A senha deve conter letras.', 'mixed' => 'A senha deve conter letras maiúsculas e minúsculas.', 'numbers' => 'A senha deve conter números.', 'symbols' => 'A senha deve conter símbolos.'],
    'attributes' => ['identificador' => 'o identificador institucional', 'senha' => 'a senha', 'senha_atual' => 'a senha atual', 'nova_senha' => 'a nova senha', 'vinculo' => 'o vínculo'],
];
