<?php

namespace Laramod\Admin\Contracts;

interface ProvidesDashboardScripts
{
    /**
     * Get the scripts the admin loads before it mounts, so they can register the module's pages.
     *
     * A script is one of the module's Vite entries, relative to the module and declared by viteEntries() too:
     * "resources/js/dashboard.tsx".
     *
     * @return list<string>
     */
    public function dashboardScripts(): array;
}
