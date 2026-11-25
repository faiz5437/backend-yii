<?php

namespace app\models;

use yii\db\ActiveRecord;

class Pengaturan extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'pengaturan';
    }
}
