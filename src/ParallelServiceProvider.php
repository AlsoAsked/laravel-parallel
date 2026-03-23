<?php

namespace Recca0120\LaravelParallel;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Recca0120\LaravelParallel\Console\ParallelCommand;

class ParallelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $databaseName = 'laravel-parallel.sqlite';
        $testToken = \getenv('UNIQUE_TEST_TOKEN');

        if ($testToken) {
            $databaseName = $testToken . '_' . $databaseName;
        }

        config([
            'database.connections.laravel-parallel' => [
                'driver' => 'sqlite',
                'database' => database_path($databaseName),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $this->commands([ParallelCommand::class]);

        $this->app->bind(ParallelRequest::class, function () {
            /** @var Request $request */
            $request = app('request');

            return (new ParallelRequest($request))->withServerVariables($request->server->all());
        });
    }
}
