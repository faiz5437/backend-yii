<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\models\Icd10;

class Icd10Controller extends MasterController
{
    protected string $modelClass = Icd10::class;
    protected array $searchable = ['kode', 'nama'];
    protected array $defaultOrder = ['kode' => SORT_ASC];
}
