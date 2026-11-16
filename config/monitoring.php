<?php

return [
    'health' => [
        'enabled' => true,
        'endpoint' => '/api/v1/health',
    ],
    'alerts' => [
        'email' => env('ALERT_EMAIL', 'ops@example.com'),
    ],
];
