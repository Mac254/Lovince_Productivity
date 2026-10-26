<?php

return [

    /*
    | The account `php artisan db:seed` creates. Keep these in .env only — never commit them.
    */
    'owner' => [
        'name' => env('FOCUS_OWNER_NAME'),
        'email' => env('FOCUS_OWNER_EMAIL'),
        'password' => env('FOCUS_OWNER_PASSWORD'),
    ],

    /*
    | Fill an empty account with sample goals and tasks when seeding. Ignored outside local/testing.
    */
    'seed_demo' => (bool) env('FOCUS_SEED_DEMO', false),

    /*
    | One log line per API request (method, path, status, duration, user). Watch with `php artisan pail`.
    */
    /*
    | How long "Keep me signed in" lasts on a device. Renews each time the device is used.
    */
    'remember_days' => (int) env('FOCUS_REMEMBER_DAYS', 30),

    'log_requests' => (bool) env('FOCUS_LOG_REQUESTS', true),

];
