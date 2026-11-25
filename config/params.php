<?php

return [
    'jwt' => [
        'privateKeyPath' => dirname(__DIR__) . '/' . env('JWT_PRIVATE_KEY_PATH', 'keys/jwt-private.pem'),
        'publicKeyPath' => dirname(__DIR__) . '/' . env('JWT_PUBLIC_KEY_PATH', 'keys/jwt-public.pem'),
        'issuer' => env('JWT_ISSUER', 'simrs-yii'),
        'accessTtl' => (int) env('JWT_ACCESS_TTL', 900),
        'refreshTtl' => (int) env('JWT_REFRESH_TTL', 604800),
    ],
    'serviceKey' => env('SERVICE_KEY'),
    'anjungan' => [
        'rateLimit' => (int) env('ANJUNGAN_RATE_LIMIT', 20),
        'rateLimitPasienBaru' => (int) env('ANJUNGAN_RATE_LIMIT_PASIEN_BARU', 5),
    ],
    'corsOrigins' => array_filter(explode(',', (string) env('CORS_ORIGINS', 'http://localhost:5173'))),
];
