<?php

return [
    'class' => yii\db\Connection::class,
    'dsn' => sprintf('pgsql:host=%s;port=%s;dbname=%s', env('DB_HOST', '127.0.0.1'), env('DB_PORT', 5432), env('DB_NAME', 'simrs')),
    'username' => env('DB_USER', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
];
