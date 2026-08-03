<?php

namespace Uadevteampackages\Imitator\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Uadevteampackages\Imitator\ImitatorServiceProvider;
use Uadevteampackages\Imitator\Tests\Models\User;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ImitatorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app->detectEnvironment(fn (): string => 'local');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'local');
        $app['config']->set('imitator.enabled', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('imitator.user', [
            'model' => User::class,
            'unique_by' => 'id',
            'attributes' => [
                'id' => 'id',
                'name' => 'name',
                'email' => 'email',
                'principal_name' => 'userPrincipalName',
                'username' => 'username',
                'job_title' => 'job_title',
            ],
            'create_only' => [],
        ]);
        $app['config']->set('imitator.inject_banner', true);
        $app['config']->set('imitator.azure', [
            'tenant_id' => 'test-tenant',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
