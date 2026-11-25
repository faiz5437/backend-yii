<?php

namespace app\models;

use yii\db\ActiveQuery;

class Resep extends BaseModel
{
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_DISERAHKAN = 'diserahkan';
    public const STATUS_BATAL = 'batal';

    public static function tableName(): string
    {
        return 'resep';
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(ResepItem::class, ['resep_id' => 'id']);
    }

    public function getKunjungan(): ActiveQuery
    {
        return $this->hasOne(Kunjungan::class, ['id' => 'kunjungan_id']);
    }

    public function getDokter(): ActiveQuery
    {
        return $this->hasOne(TenagaMedis::class, ['id' => 'dokter_id']);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'total' => fn () => array_sum(array_map(fn (ResepItem $i) => $i->jumlah * (float) $i->harga_satuan, $this->items)),
        ]);
    }

    public function extraFields(): array
    {
        return ['items', 'kunjungan', 'dokter'];
    }
}
