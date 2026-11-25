<?php

return [
    'id' => 'simrs-console',
    'basePath' => dirname(__DIR__),
    'language' => 'id-ID',
    'timeZone' => 'Asia/Jakarta',
    'controllerNamespace' => 'app\commands',
    'components' => [
        'db' => require __DIR__ . '/db.php',
        'cache' => yii\caching\FileCache::class,
        'authManager' => ['class' => yii\rbac\DbManager::class],
        'jwt' => ['class' => app\components\JwtService::class],
    ],
    'params' => require __DIR__ . '/params.php',
];
