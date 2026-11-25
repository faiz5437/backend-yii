<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use app\services\LabService;
use Yii;

/**
 * Endpoint khusus komunikasi antar-service (Node.js -> Yii), diamankan dengan X-Service-Key.
 */
class InternalController extends ApiController
{
    protected array $publicActions = ['hasil-lab'];

    protected function verbs(): array
    {
        return ['hasil-lab' => ['POST']];
    }

    public function beforeAction($action): bool
    {
        $key = (string) Yii::$app->request->headers->get('X-Service-Key');
        $expected = (string) Yii::$app->params['serviceKey'];

        if ($expected === '' || !hash_equals($expected, $key)) {
            throw ApiException::unauthorized(Message::SERVICE_KEY_INVALID);
        }

        return parent::beforeAction($action);
    }

    public function actionHasilLab(): array
    {
        $result = (new LabService())->simpanHasilIntegrasi($this->body());

        return ResponseHelper::success($result, Message::LAB_HASIL_DISIMPAN);
    }
}
