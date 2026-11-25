<?php

namespace app\models;

use yii\db\ActiveQuery;

class LabOrder extends BaseModel
{
    public const STATUS_DIMINTA = 'diminta';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_BATAL = 'batal';

    public static function tableName(): string
    {
        return 'lab_order';
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(LabOrderItem::class, ['lab_order_id' => 'id']);
    }

    public function getKunjungan(): ActiveQuery
    {
        return $this->hasOne(Kunjungan::class, ['id' => 'kunjungan_id']);
    }

    public function getDokter(): ActiveQuery
    {
        return $this->hasOne(TenagaMedis::class, ['id' => 'dokter_id']);
    }

    public function extraFields(): array
    {
        return ['items', 'kunjungan', 'dokter'];
    }
}
