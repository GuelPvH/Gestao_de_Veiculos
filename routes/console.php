<?php

use App\Services\Auth\PasswordPolicy;
use App\Services\Auth\PasswordVerifier;
use App\Services\Auth\ProcedureRunner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fleet:create-admin', function (): int {
    $identificador = $this->ask('Identificador institucional');
    $nome = $this->ask('Nome');
    $email = $this->ask('E-mail');
    $senha = $this->secret('Senha privada (mínimo 12 caracteres)');
    $dados = ['identificador' => $identificador, 'nome' => $nome, 'email' => $email, 'nova_senha' => $senha, 'nova_senha_confirmation' => $senha];
    $validacao = Validator::make($dados, ['identificador' => 'required|string|max:100', 'nome' => 'required|string|max:150', 'email' => 'required|email|max:254', 'nova_senha' => PasswordPolicy::rules()]);
    if ($validacao->fails()) {
        $this->error('Dados inválidos. Revise a política de senha e os campos.');

        return 1;
    }
    if (! $this->confirm('Criar o administrador inicial no banco configurado, sem registrar a senha?')) {
        return 1;
    }
    try {
        app(ProcedureRunner::class)->call('sp_criar_administrador_inicial', [$identificador, $nome, $email, app(PasswordVerifier::class)->make($senha)]);
    } catch (Throwable) {
        $this->error('Inicialização recusada. Confira o provisionamento e os privilégios privadamente.');

        return 1;
    }
    $this->info('Administrador inicial criado.');

    return 0;
})->purpose('Inicializar somente o primeiro administrador, após provisionamento conferido');
