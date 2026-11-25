<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\services\PengaturanService;

class PengaturanController extends ApiController
{
    protected function permissions(): array
    {
        return ['index' => 'kelolaPengaturan', 'update' => 'kelolaPengaturan'];
    }

    protected function verbs(): array
    {
        return ['index' => ['GET'], 'update' => ['PUT']];
    }

    public function actionIndex(): array
    {
        return ResponseHelper::success(PengaturanService::all(), Message::FETCHED);
    }

    public function actionUpdate(): array
    {
        return ResponseHelper::success(PengaturanService::update($this->body(), $this->userId()), Message::PENGATURAN_DISIMPAN);
    }
}
