<?php

namespace app\models;

class JenisPemeriksaanLab extends BaseModel
{
    public static function tableName(): string
    {
        return 'jenis_pemeriksaan_lab';
    }

    protected static function decimalFields(): array
    {
        return ['tarif'];
    }

    public function rules(): array
    {
        return [
            [['kode', 'nama', 'tarif'], 'required'],
            [['kode', 'nama', 'satuan', 'nilai_rujukan'], 'trim'],
            ['kode', 'filter', 'filter' => 'strtoupper'],
            ['kode', 'string', 'max' => 20],
            ['kode', 'unique'],
            ['nama', 'string', 'max' => 150],
            ['satuan', 'string', 'max' => 30],
            ['nilai_rujukan', 'string', 'max' => 100],
            ['tarif', 'number', 'min' => 0],
            ['is_active', 'boolean'],
        ];
    }
}
