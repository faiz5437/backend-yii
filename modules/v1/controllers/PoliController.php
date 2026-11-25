<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\models\Poli;

class PoliController extends MasterController
{
    protected string $modelClass = Poli::class;
    protected array $searchable = ['kode', 'nama'];
}
