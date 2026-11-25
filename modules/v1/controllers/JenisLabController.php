<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\models\JenisPemeriksaanLab;

class JenisLabController extends MasterController
{
    protected string $modelClass = JenisPemeriksaanLab::class;
    protected array $searchable = ['kode', 'nama'];
}
