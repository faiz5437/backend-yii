<?php

namespace app\models;

use yii\db\ActiveQuery;

class ResepItem extends BaseModel
{
    public static function tableName(): string
    {
        return 'resep_item';
    }

    protected static function decimalFields(): array
    {
        return ['harga_satuan'];
    }

    public function getObat(): ActiveQuery
    {
        return $this->hasOne(Obat::class, ['id' => 'obat_id']);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'nama_obat' => fn () => $this->obat->nama ?? null,
            'satuan' => fn () => $this->obat->satuan ?? null,
            'stok_tersedia' => fn () => $this->obat->stok ?? null,
            'subtotal' => fn () => $this->jumlah * (float) $this->harga_satuan,
        ]);
    }
}
