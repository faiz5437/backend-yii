<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\ResponseHelper;
use Yii;

class SiteController extends ApiController
{
    protected array $publicActions = ['health'];

    protected function verbs(): array
    {
        return ['health' => ['GET']];
    }

    public function actionHealth(): array
    {
        $database = (bool) Yii::$app->db->createCommand('SELECT 1')->queryScalar();

        return ResponseHelper::success(['service' => 'simrs-yii', 'database' => $database ? 'up' : 'down']);
    }
}
