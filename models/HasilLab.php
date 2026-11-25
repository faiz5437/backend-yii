<?php

namespace app\models;

class HasilLab extends BaseModel
{
    public const FLAG = ['normal', 'tinggi', 'rendah', 'kritis'];

    public static function tableName(): string
    {
        return 'hasil_lab';
    }

    public function rules(): array
    {
        return [
            [['nilai'], 'required'],
            [['nilai', 'catatan'], 'trim'],
            ['nilai', 'string', 'max' => 100],
            ['flag', 'in', 'range' => self::FLAG],
            ['flag', 'default', 'value' => null],
            ['catatan', 'string'],
        ];
    }
}
