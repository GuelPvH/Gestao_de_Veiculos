<?php

use App\Services\Auth\IdentityService;
use App\Support\Cpf;
use App\Support\Value;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fleet:create-admin', function (): int {
    $cpf = Cpf::normalize(Value::string($this->ask('CPF')));
    $nome = Value::string($this->ask('Nome'));
    $email = Value::nullableString($this->ask('E-mail de contato (opcional)'));
    $validacao = Validator::make(['cpf' => $cpf, 'nome' => $nome, 'email' => $email], [
        'cpf' => 'required', 'nome' => 'required|string|max:150', 'email' => 'nullable|email|max:254',
    ]);
    if ($validacao->fails()) {
        $this->error('Dados inválidos. Confira o CPF, o nome e o e-mail de contato.');

        return 1;
    }
    if (! $this->confirm('Criar o administrador inicial no banco configurado e emitir um código temporário?')) {
        return 1;
    }
    try {
        $administrador = app(IdentityService::class)->createInitialAdministrator(Value::string($cpf), $nome, $email);
    } catch (Throwable) {
        $this->error('Inicialização recusada. Confira o provisionamento e os privilégios privadamente.');

        return 1;
    }
    $this->info('Administrador criado. Use o CPF e este código temporário no primeiro acesso.');
    $this->line($administrador['codigo']);

    return 0;
})->purpose('Inicializar somente o primeiro administrador, após provisionamento conferido');

Schedule::command('fleet:dispatch-mail --limit=100')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => Cache::store(Value::string(config('cache.default')))->put('fleet:heartbeat:scheduler', time(), 120))
    ->name('fleet:scheduler-heartbeat')->everyMinute();
$mailQueueConnection = config('fleet.recovery_queue_connection', 'database');
Schedule::command('queue:monitor '.$mailQueueConnection.':auth-mails,'.$mailQueueConnection.':security-mails --max=100')
    ->everyMinute()->withoutOverlapping();

Schedule::command('fleet:delivery-health')->everyFiveMinutes()->withoutOverlapping();
