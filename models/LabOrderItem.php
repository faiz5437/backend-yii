<?php

namespace app\models;

use yii\db\ActiveQuery;

class LabOrderItem extends BaseModel
{
    public static function tableName(): string
    {
        return 'lab_order_item';
    }

    protected static function decimalFields(): array
    {
        return ['tarif'];
    }

    public function getJenis(): ActiveQuery
    {
        return $this->hasOne(JenisPemeriksaanLab::class, ['id' => 'jenis_pemeriksaan_id']);
    }

    public function getHasil(): ActiveQuery
    {
        return $this->hasOne(HasilLab::class, ['lab_order_item_id' => 'id']);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'kode' => fn () => $this->jenis->kode ?? null,
            'nama' => fn () => $this->jenis->nama ?? null,
            'satuan' => fn () => $this->jenis->satuan ?? null,
            'nilai_rujukan' => fn () => $this->jenis->nilai_rujukan ?? null,
            'hasil' => fn () => $this->hasil,
        ]);
    }
}
