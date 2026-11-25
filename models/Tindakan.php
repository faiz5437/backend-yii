<?php

namespace app\models;

class Tindakan extends BaseModel
{
    public static function tableName(): string
    {
        return 'tindakan';
    }

    protected static function decimalFields(): array
    {
        return ['tarif'];
    }

    public function rules(): array
    {
        return [
            [['kode', 'nama', 'tarif'], 'required'],
            [['kode', 'nama', 'kategori'], 'trim'],
            ['kode', 'filter', 'filter' => 'strtoupper'],
            ['kode', 'string', 'max' => 20],
            ['kode', 'unique'],
            ['nama', 'string', 'max' => 150],
            ['kategori', 'string', 'max' => 50],
            ['tarif', 'number', 'min' => 0],
            ['is_active', 'boolean'],
        ];
    }
}
