<?php

namespace app\models;

use yii\db\ActiveRecord;

class Icd10 extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'icd10';
    }

    public function rules(): array
    {
        return [
            [['kode', 'nama'], 'required'],
            ['kode', 'filter', 'filter' => 'strtoupper'],
            ['kode', 'match', 'pattern' => '/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/', 'message' => 'Format kode ICD-10 tidak valid.'],
            ['kode', 'unique'],
            ['nama', 'string', 'max' => 255],
        ];
    }
}
