<?php

// Executável antes do Composer. Nunca substitui uma chave existente.
$caminho = dirname(__DIR__, 2).'/.env';
if (! is_file($caminho)) {
    copy(dirname(__DIR__, 2).'/.env.example', $caminho);
}
$conteudo = file_get_contents($caminho);
if ($conteudo === false) {
    throw new RuntimeException('Não foi possível ler a configuração privada.');
}
if (preg_match('/^APP_KEY=.+$/m', $conteudo)) {
    echo "Chave existente preservada.\n";
    exit(0);
}
$chave = 'APP_KEY=base64:'.base64_encode(random_bytes(32));
$conteudo = preg_match('/^APP_KEY=.*$/m', $conteudo)
    ? preg_replace('/^APP_KEY=.*$/m', $chave, $conteudo)
    : $conteudo."\n".$chave."\n";
$temporario = $caminho.'.tmp';
if (file_put_contents($temporario, $conteudo, LOCK_EX) === false) {
    throw new RuntimeException('Não foi possível salvar a chave.');
}
chmod($temporario, 0600);
if (! rename($temporario, $caminho)) {
    throw new RuntimeException('Não foi possível concluir a configuração.');
}
echo "Chave inicial criada.\n";
