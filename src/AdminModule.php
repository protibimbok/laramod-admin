<?php

namespace Laramod\Admin;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Laramod\Admin\Console\InitCommand;
use Laramod\Admin\Http\Controllers\AdminController;
use Laramod\Contracts\Module;
use Laramod\Contracts\ProvidesCommands;
use Laramod\Contracts\ProvidesConfig;
use Laramod\Contracts\ProvidesRoutes;
use Laramod\Contracts\ProvidesViews;
use Laramod\Contracts\ProvidesViteEntries;

class AdminModule implements Module, ProvidesCommands, ProvidesConfig, ProvidesRoutes, ProvidesViews, ProvidesViteEntries
{
    /**
     * The entry that mounts the admin, once every dashboard script has run.
     */
    public const string MAIN = 'resources/js/main.tsx';

    /**
     * Get the unique, kebab-case name of the module.
     */
    public function name(): string
    {
        return 'admin';
    }

    /**
     * Get the absolute path to the module's root directory.
     */
    public function path(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Get the module's Artisan commands.
     *
     * @return list<class-string<Command>>
     */
    public function commands(): array
    {
        return [InitCommand::class];
    }

    /**
     * Get the module's configuration files, keyed by the configuration key they are merged into.
     *
     * @return array<string, string>
     */
    public function config(): array
    {
        return ['modules.admin' => 'config/admin.php'];
    }

    /**
     * Register the one route of the admin: every URL beneath its path shows the page it mounts in.
     *
     * It is a fallback route, so a route of the application or of a module beneath the same path wins.
     */
    public function routes(Router $router): void
    {
        $router->get(trim(Config::get('modules.admin.path'), '/').'/{any?}', AdminController::class)
            ->where('any', '.*')
            ->middleware(Config::get('modules.admin.middleware'))
            ->name('admin')
            ->fallback();
    }

    /**
     * Get the directory of the module's views.
     */
    public function views(): string
    {
        return 'resources/views';
    }

    /**
     * Get the source files the module expects Vite to build.
     *
     * @return list<string>
     */
    public function viteEntries(): array
    {
        return [self::MAIN];
    }
}
