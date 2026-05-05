<?php

return [
    'super_admin' => [
        'name' => env('PRODUCTION_SUPER_ADMIN_NAME', 'JPrime Super Admin'),
        'email' => env('PRODUCTION_SUPER_ADMIN_EMAIL', 'admin@jprimefitness.ph'),
        'password' => env('PRODUCTION_SUPER_ADMIN_PASSWORD'),
    ],
];
