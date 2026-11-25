<?php

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true' => true,
        'false' => false,
        default => $value,
    };
}

defined('YII_DEBUG') or define('YII_DEBUG', (bool) env('APP_DEBUG', false));
defined('YII_ENV') or define('YII_ENV', env('APP_ENV', 'prod'));

require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
