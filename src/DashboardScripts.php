<?php

namespace Laramod\Admin;

use InvalidArgumentException;
use Laramod\Admin\Contracts\ProvidesDashboardScripts;
use Laramod\Admin\Contracts\ProvidesDashboardScriptUrls;
use Laramod\Contracts\Module;
use Laramod\Contracts\ProvidesViteEntries;
use Laramod\ModuleRegistry;

class DashboardScripts
{
    public function __construct(protected ModuleRegistry $modules) {}

    /**
     * Get the Vite entries the admin is made of, named the way Vite's manifest names them.
     *
     * The modules' scripts come in the order of the modules, and the admin itself comes last:
     * module scripts run in document order, so every page is registered by the time the admin mounts.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->modules->providing(ProvidesDashboardScripts::class) as $module) {
            $names = $this->names($module);

            foreach ($module->dashboardScripts() as $script) {
                $entries[] = $names[$script] ?? throw new InvalidArgumentException(sprintf(
                    'Dashboard script [%s] is not declared by [%s::viteEntries()].', $script, $module::class,
                ));
            }
        }

        return [...$entries, $this->names(new AdminModule)[AdminModule::MAIN]];
    }

    /**
     * Get the URLs of the scripts that are loaded from where they are, outside the application's build.
     *
     * @return list<string>
     */
    public function urls(): array
    {
        $urls = [];

        foreach ($this->modules->providing(ProvidesDashboardScriptUrls::class) as $module) {
            $urls = [...$urls, ...$module->dashboardScriptUrls()];
        }

        return $urls;
    }

    /**
     * Get the names Vite's manifest has for the entries of a module, keyed by the entries as the module declares them.
     *
     * @return array<string, string>
     */
    protected function names(Module $module): array
    {
        if (! $module instanceof ProvidesViteEntries) {
            return [];
        }

        return array_combine($module->viteEntries(), $this->modules->viteEntries($module));
    }
}
