<?php

namespace Laramod\Admin\Tests;

use Laramod\Admin\AdminModule;
use Laramod\LaramodServiceProvider;
use Laramod\ModuleRegistry;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Get the package providers.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LaramodServiceProvider::class];
    }

    /**
     * Register the module before the application boots, the way bootstrap/modules.php does.
     */
    protected function defineEnvironment($app): void
    {
        $app->afterResolving(ModuleRegistry::class, function (ModuleRegistry $registry): void {
            $registry->register(AdminModule::class);
        });
    }
}
