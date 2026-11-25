<?php

namespace app\models;

class TagihanItem extends BaseModel
{
    public const SUMBER_PENDAFTARAN = 'pendaftaran';
    public const SUMBER_TINDAKAN = 'tindakan';
    public const SUMBER_LAB = 'lab';
    public const SUMBER_OBAT = 'obat';

    public static function tableName(): string
    {
        return 'tagihan_item';
    }

    protected static function decimalFields(): array
    {
        return ['harga_satuan', 'subtotal'];
    }
}
