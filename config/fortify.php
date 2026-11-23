<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Fortify provides the authentication back end (routes and controllers).
    | The views are plain Blade files registered in FortifyServiceProvider.
    |
    */

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    // Where users land after logging in. The "/" route redirects by role.
    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
    ],

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Registration is deliberately absent: this is an internal tool and
    | accounts are created by an administrator.
    |
    */

    'features' => [
        Features::resetPasswords(),
        Features::updatePasswords(),
    ],

];
