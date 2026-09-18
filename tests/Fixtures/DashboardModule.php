<?php

namespace Laramod\Admin\Tests\Fixtures;

use Laramod\Admin\Contracts\ProvidesDashboardScripts;
use Laramod\Admin\Contracts\ProvidesDashboardScriptUrls;
use Laramod\Contracts\Module;
use Laramod\Contracts\ProvidesViteEntries;

class DashboardModule implements Module, ProvidesDashboardScripts, ProvidesDashboardScriptUrls, ProvidesViteEntries
{
    /**
     * @param  list<string>  $viteEntries
     * @param  list<string>  $dashboardScripts
     * @param  list<string>  $dashboardScriptUrls
     */
    public function __construct(
        protected string $name,
        protected array $viteEntries,
        protected array $dashboardScripts,
        protected array $dashboardScriptUrls = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * The module lives in the application, the way "make:module" creates one.
     */
    public function path(): string
    {
        return base_path('Modules/'.ucfirst($this->name));
    }

    public function viteEntries(): array
    {
        return $this->viteEntries;
    }

    public function dashboardScripts(): array
    {
        return $this->dashboardScripts;
    }

    public function dashboardScriptUrls(): array
    {
        return $this->dashboardScriptUrls;
    }
}
