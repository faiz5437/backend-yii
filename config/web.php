<?php

$config = [
    'id' => 'simrs-yii',
    'name' => 'SIMRS Rawat Jalan',
    'basePath' => dirname(__DIR__),
    'language' => 'id-ID',
    'timeZone' => 'Asia/Jakarta',
    'bootstrap' => ['log'],
    'modules' => [
        'v1' => app\modules\v1\Module::class,
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => env('COOKIE_VALIDATION_KEY', 'dev-only-key'),
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
        ],
        'user' => [
            'identityClass' => app\models\User::class,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'authManager' => [
            'class' => yii\rbac\DbManager::class,
            'cache' => YII_DEBUG ? null : 'cache',
        ],
        'cache' => yii\caching\FileCache::class,
        'errorHandler' => [
            'class' => app\components\ErrorHandler::class,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                ['class' => yii\log\FileTarget::class, 'levels' => ['error', 'warning']],
            ],
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => require __DIR__ . '/routes.php',
        ],
        'jwt' => [
            'class' => app\components\JwtService::class,
        ],
    ],
    'params' => require __DIR__ . '/params.php',
];

return $config;
