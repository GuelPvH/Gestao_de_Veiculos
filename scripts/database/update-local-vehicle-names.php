<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (config('database.connections.mysql.host') !== 'mysql-local' || DB::connection()->getDatabaseName() !== 'frota_pf_local') throw new RuntimeException('Permitido apenas no banco local.');
$models = [['Toyota', 'Corolla'], ['Chevrolet', 'Onix'], ['Fiat', 'Strada'], ['Volkswagen', 'Gol'], ['Hyundai', 'HB20'], ['Renault', 'Duster'], ['Toyota', 'Hilux'], ['Chevrolet', 'S10'], ['Ford', 'Ranger'], ['Jeep', 'Renegade'], ['Honda', 'Civic'], ['Nissan', 'Versa'], ['Fiat', 'Toro'], ['Volkswagen', 'Polo'], ['Renault', 'Logan'], ['Chevrolet', 'Spin'], ['Hyundai', 'Creta'], ['Honda', 'HR-V'], ['Nissan', 'Frontier'], ['Jeep', 'Compass']];
$count = DB::transaction(function () use ($models) {
    $vehicles = DB::table('veiculos')->where('marca', 'Teste')->where('modelo', 'Desenvolvimento')->where('nome', 'like', '[TESTE] Veiculo %')->orderBy('id')->get();
    foreach ($vehicles as $index => $vehicle) {
        [$brand, $model] = $models[$index % count($models)];
        DB::table('veiculos')->where('id', $vehicle->id)->update(['nome' => $brand.' '.$model, 'marca' => $brand, 'modelo' => $model]);
    }
    return $vehicles->count();
});
echo $count." veiculos atualizados.\n";
