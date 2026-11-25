<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\models\Tindakan;

class TindakanController extends MasterController
{
    protected string $modelClass = Tindakan::class;
    protected array $searchable = ['kode', 'nama', 'kategori'];
    protected array $filterable = ['kategori'];
}
