<?php

declare(strict_types=1);

namespace Zufarmarwah\PerformanceGuard\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Zufarmarwah\PerformanceGuard\PerformanceGuardServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PerformanceGuardServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }
}
