<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Path
    |--------------------------------------------------------------------------
    |
    | The admin answers every URL beneath this path and leaves the routing
    | of what comes after it to its pages. A route of yours beneath the
    | same path, "admin/export" for example, still takes precedence.
    |
    */

    'path' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Admin Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware are applied on top of the "web" group the routes of
    | every module already run in. "auth" lets every signed in user in,
    | so add what decides who is an admin, such as "can:access-admin".
    |
    */

    'middleware' => ['auth'],

];
