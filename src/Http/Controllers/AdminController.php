<?php

namespace Laramod\Admin\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View as Views;
use Laramod\Admin\DashboardScripts;

class AdminController
{
    /**
     * Show the page the admin mounts in. Which page of the admin is shown is for its own router to decide.
     */
    public function __invoke(Request $request, DashboardScripts $scripts): View
    {
        return Views::make('admin::app', [
            'entries' => $scripts->entries(),
            'urls' => $scripts->urls(),
            'boot' => [
                'appName' => Config::get('app.name'),
                'basePath' => $request->getBaseUrl().'/'.trim(Config::get('modules.admin.path'), '/'),
                // The admin has no sign out of its own: it offers the application's, when there is a "logout" route.
                'logoutUrl' => Route::has('logout') ? URL::route('logout') : null,
                'viewer' => $request->user()?->only(['id', 'name', 'email']),
            ],
        ]);
    }
}
