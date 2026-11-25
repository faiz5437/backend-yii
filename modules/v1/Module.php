<?php

namespace app\modules\v1;

/**
 * API versi 1. Controller dan routing per versi berada di modul ini,
 * sedangkan model dan service dipakai bersama oleh semua versi.
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\v1\controllers';
}
