<?php

namespace app\models;

use yii\db\ActiveQuery;

class Diagnosis extends BaseModel
{
    public static function tableName(): string
    {
        return 'diagnosis';
    }

    public function getIcd10(): ActiveQuery
    {
        return $this->hasOne(Icd10::class, ['kode' => 'icd10_kode']);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), ['nama' => fn () => $this->icd10->nama ?? null]);
    }
}
