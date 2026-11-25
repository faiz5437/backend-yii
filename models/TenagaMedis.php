<?php

namespace app\models;

use yii\db\ActiveQuery;

class TenagaMedis extends BaseModel
{
    public const JENIS = ['dokter', 'perawat', 'apoteker', 'analis_lab'];

    public static function tableName(): string
    {
        return 'tenaga_medis';
    }

    public function rules(): array
    {
        return [
            [['nip', 'nama', 'jenis'], 'required'],
            [['nip', 'no_sip', 'nama', 'spesialisasi', 'no_hp'], 'trim'],
            ['nip', 'match', 'pattern' => '/^[0-9]{8,30}$/', 'message' => 'NIP hanya berisi angka (8-30 digit).'],
            ['no_sip', 'string', 'max' => 50],
            ['nama', 'string', 'max' => 150],
            ['spesialisasi', 'string', 'max' => 100],
            ['no_hp', 'match', 'pattern' => '/^[0-9+]{8,20}$/'],
            ['jenis', 'in', 'range' => self::JENIS],
            [['nip', 'no_sip'], 'unique'],
            ['no_sip', 'default', 'value' => null],
            ['poli_id', 'exist', 'targetClass' => Poli::class, 'targetAttribute' => 'id'],
            ['poli_id', 'required', 'when' => fn () => $this->jenis === 'dokter', 'message' => 'Dokter wajib ditempatkan di poli.'],
            ['is_active', 'boolean'],
        ];
    }

    public function getPoli(): ActiveQuery
    {
        return $this->hasOne(Poli::class, ['id' => 'poli_id']);
    }

    public function extraFields(): array
    {
        return ['poli'];
    }
}
