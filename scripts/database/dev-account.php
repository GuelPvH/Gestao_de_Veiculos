<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Provisionamento disponível somente pelo terminal local.');
}
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.host') !== 'mysql-local'
    || DB::connection()->getDatabaseName() !== 'frota_pf_local') {
    fwrite(STDERR, 'Conta fictícia permitida somente no ambiente local mysql-local/frota_pf_local.'.PHP_EOL);
    exit(1);
}
$password = getenv('FLEET_DEV_PASSWORD');
if (! is_string($password) || strlen($password) < 16) {
    fwrite(STDERR, 'Credencial privada de desenvolvimento ausente.'.PHP_EOL);
    exit(1);
}
try {
    $result = DB::transaction(function () use ($password): array {
        DB::table('configuracao_sistema')->where('id', 1)->lockForUpdate()->first();
        $user = DB::table('usuarios')->where('identificador', 'dev.frota')->lockForUpdate()->first();
        $created = $user === null;
        if ($user && ($user->nome !== '[TESTE] Desenvolvimento' || $user->email !== 'dev.frota@example.invalid'
            || ! Hash::check($password, $user->senha_hash) || ! $user->ativo || $user->deve_trocar_senha)) {
            throw new RuntimeException('A conta existente foi preservada; confira o arquivo privado de desenvolvimento.');
        }
        if ($created) {
            $id = DB::table('usuarios')->insertGetId([
                'unidade_id' => 1, 'identificador' => 'dev.frota', 'nome' => '[TESTE] Desenvolvimento',
                'email' => 'dev.frota@example.invalid', 'senha_hash' => Hash::make($password),
                'estado_identidade' => 'legado', 'ativo' => 1, 'deve_trocar_senha' => 0,
            ]);
            DB::table('preferencias_usuario')->insert(['usuario_id' => $id, 'tema' => 'dispositivo']);
        } else {
            $id = (int) $user->id;
        }
        $roles = DB::table('perfis')->where('ativo', 1)->whereIn('codigo', ['gestor', 'servidor', 'financeiro', 'administrador'])->get();
        if ($roles->count() !== 4) {
            throw new RuntimeException('Os quatro perfis canônicos precisam estar ativos.');
        }
        $grants = 0;
        foreach ($roles as $role) {
            $exists = DB::table('usuario_perfis')->where('usuario_id', $id)->where('perfil_id', $role->id)
                ->where('unidade_id', 1)->where('ativo', 1)->where('vigente_desde', '<=', now('UTC'))->whereNull('vigente_ate')->exists();
            if (! $exists) {
                DB::table('usuario_perfis')->insert(['usuario_id' => $id, 'perfil_id' => $role->id, 'unidade_id' => 1, 'vigente_desde' => now('UTC')->format('Y-m-d H:i:s.u')]);
                $grants++;
            }
        }
        if ($created || $grants > 0) {
            DB::table('auditoria')->insert(['evento' => 'carga_teste_local', 'entidade' => 'usuarios', 'entidade_id' => $id, 'descricao' => 'Conta fictícia de desenvolvimento com quatro perfis; MFA administrativo preservado.']);
        }

        return ['identifier' => 'dev.frota', 'created' => $created, 'profiles' => $roles->pluck('codigo')->sort()->values()->all(), 'newGrants' => $grants];
    });
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $error) {
    if ($error instanceof QueryException || $error instanceof PDOException) {
        report($error);
    }
    fwrite(STDERR, 'Não foi possível provisionar a conta local. Dados existentes preservados; confira a configuração e o log sanitizado.'.PHP_EOL);
    exit(1);
}
