<?php

namespace App\Support\Debugbar;

use Fruitcake\LaravelDebugbar\DataCollector\QueryCollector;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Routing\Router;

class DatabaseTimingCollector
{
    public function __invoke(LaravelDebugbar $debugbar, Dispatcher $events, Router $router): void
    {
        $collector = new QueryCollector;
        $collector->setLimits(100, 300);
        $collector->setRenderSqlWithParams(false);
        $collector->setSlowThreshold(100);
        $collector->setDurationBackground(true);
        $collector->setFindSource(true, $router->getMiddleware());
        $collector->mergeBacktraceExcludePaths([__FILE__]);
        $collector->setTimeDataCollector($debugbar->getTimeCollector());
        $debugbar->addCollector($collector);

        $events->listen(QueryExecuted::class, function (QueryExecuted $query) use ($debugbar, $collector): void {
            if (! $debugbar->isEnabled()) {
                return;
            }
            // Redact the telemetry copy; execution and other database listeners keep their bindings.
            $telemetria = clone $query;
            $telemetria->bindings = [];
            $collector->addQuery($telemetria);
        });

        foreach ([TransactionBeginning::class => 'Begin Transaction', TransactionCommitted::class => 'Commit Transaction', TransactionRolledBack::class => 'Rollback Transaction'] as $evento => $descricao) {
            $events->listen($evento, function ($transaction) use ($debugbar, $collector, $descricao): void {
                if ($debugbar->isEnabled()) {
                    $collector->collectTransactionEvent($descricao, $transaction->connection);
                }
            });
        }
    }
}
