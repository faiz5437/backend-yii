<?php

namespace app\models;

class Poli extends BaseModel
{
    public static function tableName(): string
    {
        return 'poli';
    }

    protected static function decimalFields(): array
    {
        return ['tarif_pendaftaran'];
    }

    public function rules(): array
    {
        return [
            [['kode', 'nama'], 'required'],
            [['kode', 'nama'], 'trim'],
            ['kode', 'filter', 'filter' => 'strtoupper'],
            ['kode', 'string', 'max' => 20],
            ['nama', 'string', 'max' => 100],
            [['kode', 'nama'], 'unique'],
            ['tarif_pendaftaran', 'number', 'min' => 0],
            ['tarif_pendaftaran', 'default', 'value' => 0],
            ['durasi_layanan_menit', 'integer', 'min' => 1, 'max' => 240],
            ['durasi_layanan_menit', 'default', 'value' => null],
            ['is_active', 'boolean'],
        ];
    }
}
