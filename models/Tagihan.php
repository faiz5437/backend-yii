<?php

namespace app\models;

use yii\db\ActiveQuery;

class Tagihan extends BaseModel
{
    public const STATUS_BELUM_BAYAR = 'belum_bayar';
    public const STATUS_SEBAGIAN = 'sebagian';
    public const STATUS_LUNAS = 'lunas';

    public static function tableName(): string
    {
        return 'tagihan';
    }

    protected static function decimalFields(): array
    {
        return ['total_tagihan', 'total_dibayar'];
    }

    public static function statusUntuk(float $total, float $dibayar): string
    {
        return match (true) {
            $dibayar <= 0 => self::STATUS_BELUM_BAYAR,
            $dibayar < $total => self::STATUS_SEBAGIAN,
            default => self::STATUS_LUNAS,
        };
    }

    public function sisa(): float
    {
        return round((float) $this->total_tagihan - (float) $this->total_dibayar, 2);
    }

    public function getKunjungan(): ActiveQuery
    {
        return $this->hasOne(Kunjungan::class, ['id' => 'kunjungan_id']);
    }

    public function getItems(): ActiveQuery
    {
        return $this->hasMany(TagihanItem::class, ['tagihan_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function getPembayaran(): ActiveQuery
    {
        return $this->hasMany(Pembayaran::class, ['tagihan_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), ['sisa' => fn () => $this->sisa()]);
    }

    public function extraFields(): array
    {
        return ['kunjungan', 'items', 'pembayaran'];
    }
}
