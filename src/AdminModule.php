<?php

namespace Laramod\Admin;

use Illuminate\Console\Command;
use Laramod\Admin\Console\InitCommand;
use Laramod\Contracts\Module;
use Laramod\Contracts\ProvidesCommands;
use Laramod\Contracts\ProvidesViteEntries;

class AdminModule implements Module, ProvidesCommands, ProvidesViteEntries
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
     * Get the source files the module expects Vite to build.
     *
     * @return list<string>
     */
    public function viteEntries(): array
    {
        return [self::MAIN];
    }
}
