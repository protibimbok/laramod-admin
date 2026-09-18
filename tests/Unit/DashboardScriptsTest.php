<?php

namespace Laramod\Admin\Tests\Unit;

use InvalidArgumentException;
use Laramod\Admin\DashboardScripts;
use Laramod\Admin\Tests\Fixtures\DashboardModule;
use Laramod\Admin\Tests\TestCase;
use Laramod\Facades\Modules;

class DashboardScriptsTest extends TestCase
{
    public function test_the_admin_alone_is_its_own_entry(): void
    {
        $scripts = $this->app->make(DashboardScripts::class);

        $this->assertSame([dirname(__DIR__, 2).'/resources/js/main.tsx'], $scripts->entries());
        $this->assertSame([], $scripts->urls());
    }

    public function test_module_scripts_come_in_module_order_and_the_admin_comes_last(): void
    {
        Modules::register([
            new DashboardModule('blog', ['resources/js/app.ts', 'resources/js/dashboard.tsx'], ['resources/js/dashboard.tsx']),
            new DashboardModule('shop', ['resources/js/orders.tsx', 'resources/js/stock.tsx'], ['resources/js/orders.tsx', 'resources/js/stock.tsx']),
        ]);

        $this->assertSame([
            'Modules/Blog/resources/js/dashboard.tsx',
            'Modules/Shop/resources/js/orders.tsx',
            'Modules/Shop/resources/js/stock.tsx',
            dirname(__DIR__, 2).'/resources/js/main.tsx',
        ], $this->app->make(DashboardScripts::class)->entries());
    }

    public function test_urls_are_loaded_as_they_are_and_are_no_entries(): void
    {
        Modules::register(new DashboardModule(
            'blog', ['resources/js/dashboard.tsx'], ['https://cdn.example.com/chart.js', 'resources/js/dashboard.tsx'],
        ));

        $scripts = $this->app->make(DashboardScripts::class);

        $this->assertSame(['https://cdn.example.com/chart.js'], $scripts->urls());
        $this->assertSame('Modules/Blog/resources/js/dashboard.tsx', $scripts->entries()[0]);
        $this->assertCount(2, $scripts->entries());
    }

    public function test_a_script_that_is_not_a_vite_entry_of_the_module_is_rejected(): void
    {
        Modules::register(new DashboardModule('blog', ['resources/js/app.ts'], ['resources/js/dashboard.tsx']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Dashboard script [resources/js/dashboard.tsx] is not declared by ['.DashboardModule::class.'::viteEntries()].',
        );

        $this->app->make(DashboardScripts::class)->entries();
    }
}
