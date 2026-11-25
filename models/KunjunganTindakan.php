<?php

namespace app\models;

use yii\db\ActiveQuery;

class KunjunganTindakan extends BaseModel
{
    public static function tableName(): string
    {
        return 'kunjungan_tindakan';
    }

    protected static function decimalFields(): array
    {
        return ['tarif'];
    }

    public function getTindakan(): ActiveQuery
    {
        return $this->hasOne(Tindakan::class, ['id' => 'tindakan_id']);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'nama' => fn () => $this->tindakan->nama ?? null,
            'kode' => fn () => $this->tindakan->kode ?? null,
            'subtotal' => fn () => (float) $this->tarif * $this->qty,
        ]);
    }
}
