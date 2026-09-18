<?php

namespace Laramod\Admin\Contracts;

interface ProvidesDashboardScriptUrls
{
    /**
     * Get the URLs of the scripts the admin loads from where they are: "https://cdn.example.com/chart.js".
     *
     * Such a script is not part of the application's build, so it cannot import the admin to register anything.
     * What registers the module's pages is a dashboard script.
     *
     * @return list<string>
     *
     * @see ProvidesDashboardScripts
     */
    public function dashboardScriptUrls(): array;
}
