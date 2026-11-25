<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\models\TenagaMedis;

class TenagaMedisController extends MasterController
{
    protected string $modelClass = TenagaMedis::class;
    protected array $searchable = ['nama', 'nip', 'no_sip', 'spesialisasi'];
    protected array $filterable = ['jenis', 'poli_id'];
    protected array $with = ['poli'];
    protected array $expand = ['poli'];
}
