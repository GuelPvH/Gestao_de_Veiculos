<?php

namespace Tests\Feature;

use App\Support\Debugbar\DatabaseTimingCollector;
use DebugBar\DataCollector\TimeDataCollector;
use Fruitcake\LaravelDebugbar\DataCollector\QueryCollector;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugbarPrivacyTest extends TestCase
{
    public function test_query_telemetry_does_not_expose_bindings_or_change_other_listeners(): void
    {
        $events = new Dispatcher($this->app);
        $collector = null;
        $debugbar = $this->createStub(LaravelDebugbar::class);
        $debugbar->method('getTimeCollector')->willReturn(new TimeDataCollector);
        $debugbar->method('addCollector')->willReturnCallback(function (QueryCollector $value) use (&$collector, $debugbar): LaravelDebugbar {
            $collector = $value;

            return $debugbar;
        });
        $debugbar->method('isEnabled')->willReturn(true);
        (new DatabaseTimingCollector)($debugbar, $events, $this->app->make(Router::class));
        $bindings = null;
        $events->listen(QueryExecuted::class, function (QueryExecuted $query) use (&$bindings): void {
            $bindings = $query->bindings;
        });
        $query = new QueryExecuted('select ? as diagnostic', ['sample-password-session-token'], 150, DB::connection());
        $events->dispatch($query);

        $this->assertSame(['sample-password-session-token'], $bindings);
        $this->assertSame(['sample-password-session-token'], $query->bindings);
        $data = $collector->collect();
        $this->assertSame(1, $data['nb_statements']);
        $this->assertSame([], $data['statements'][0]['params']);
        $this->assertStringNotContainsString('sample-password-session-token', json_encode($data, JSON_THROW_ON_ERROR));
        $this->assertNotEmpty($data['statements'][0]['backtrace']);
        $this->assertStringNotContainsString('DatabaseTimingCollector.php', $data['statements'][0]['backtrace'][0]->name);
    }
}
