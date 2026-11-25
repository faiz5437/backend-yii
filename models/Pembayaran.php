<?php

namespace app\models;

use yii\db\ActiveQuery;

class Pembayaran extends BaseModel
{
    public const METODE = ['tunai', 'debit', 'qris', 'transfer'];
    public const METODE_PERLU_KONFIRMASI = ['qris', 'transfer'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_BERHASIL = 'berhasil';
    public const STATUS_GAGAL = 'gagal';

    public static function tableName(): string
    {
        return 'pembayaran';
    }

    protected static function decimalFields(): array
    {
        return ['jumlah', 'uang_diterima', 'kembalian'];
    }

    public function getTagihan(): ActiveQuery
    {
        return $this->hasOne(Tagihan::class, ['id' => 'tagihan_id']);
    }

    public function getKasir(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'kasir_id']);
    }

    public function extraFields(): array
    {
        return [
            'tagihan',
            'kasir' => fn () => $this->kasir ? ['id' => $this->kasir->id, 'nama' => $this->kasir->nama] : null,
        ];
    }
}
