<?php

namespace Uadevteampackages\Imitator\Tests\Feature;

use Orchestra\Testbench\TestCase as Orchestra;
use Uadevteampackages\Imitator\ImitatorServiceProvider;

class DisabledOutsideLocalTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ImitatorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->detectEnvironment(fn (): string => 'production');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'production');
        $app['config']->set('imitator.enabled', true);
    }

    public function test_routes_are_not_registered_when_env_starts_with_prod(): void
    {
        $this->get('/imitator')->assertNotFound();
    }
}
