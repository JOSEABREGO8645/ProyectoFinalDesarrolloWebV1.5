<?php

return [
    'secret' => env('JWT_SECRET'),
    'bootstrap_password' => env('LIBRERIA_ADMIN_PASSWORD'),
    'issuer' => env('APP_URL', 'http://127.0.0.1:8000'),
    'audience' => 'libreria-web',
    'access_minutes' => (int) env('JWT_ACCESS_MINUTES', 15),
    'refresh_days' => (int) env('JWT_REFRESH_DAYS', 7),
    'cookie' => 'libreria_refresh',
    'cookie_path' => '/api/auth',
    'cookie_secure' => env('JWT_COOKIE_SECURE', env('APP_ENV') === 'production'),
    'origins' => array_values(array_filter(array_map('trim', explode(',', env('FRONTEND_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))))),
];
