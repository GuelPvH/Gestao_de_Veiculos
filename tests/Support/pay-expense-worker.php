<?php

if ($argc !== 5) {
    exit(64);
}

[$script, $configPath, $inputPath, $readyPath, $resultPath] = $argv;
$configuration = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
$input = json_decode((string) file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);

if (($configuration['database'] ?? null) !== 'frota_pf_contract_tests'
    || ! in_array($configuration['host'] ?? null, ['127.0.0.1', 'localhost'], true)
    || (fileperms($configPath) & 0077) !== 0
    || ! is_int($input['vinculo'] ?? null) || ! is_int($input['despesa'] ?? null)
    || ! is_int($input['versao'] ?? null) || ! is_int($input['arquivo'] ?? null)
    || ! is_string($input['pago_em'] ?? null)) {
    exit(65);
}

try {
    $pdo = new PDO(
        'mysql:host='.$configuration['host'].';port='.(int) $configuration['port'].';dbname='.$configuration['database'].';charset=utf8mb4',
        $configuration['username'],
        $configuration['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10],
    );
    file_put_contents($readyPath, 'ready');
    chmod($readyPath, 0600);

    $statement = $pdo->prepare('CALL sp_pagar_despesa(?, ?, ?, ?, ?)');
    try {
        $statement->execute([$input['vinculo'], $input['despesa'], $input['versao'], $input['arquivo'], $input['pago_em']]);
        $statement->closeCursor();
        $resultado = 'success';
    } catch (PDOException $erro) {
        $resultado = $erro->getCode() === '45000' ? 'rejected' : 'error';
    }
    file_put_contents($resultPath, $resultado);
    chmod($resultPath, 0600);
    exit($resultado === 'error' ? 1 : 0);
} catch (Throwable) {
    file_put_contents($resultPath, 'error');
    chmod($resultPath, 0600);
    exit(1);
}
