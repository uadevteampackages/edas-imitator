<?php

namespace Uadevteampackages\Imitator\Tests\Feature;

use Orchestra\Testbench\TestCase as Orchestra;
use Uadevteampackages\Imitator\ImitatorServiceProvider;

class DisabledWhenNotEnabledTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ImitatorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->detectEnvironment(fn (): string => 'local');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'local');
        $app['config']->set('imitator.enabled', false);
    }

    public function test_routes_are_not_registered_when_imitator_enabled_is_false(): void
    {
        $this->get('/imitator')->assertNotFound();
    }
}
