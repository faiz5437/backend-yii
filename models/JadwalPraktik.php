<?php

namespace app\models;

use yii\db\ActiveQuery;

class JadwalPraktik extends BaseModel
{
    public const NAMA_HARI = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public static function tableName(): string
    {
        return 'jadwal_praktik';
    }

    public function rules(): array
    {
        return [
            [['dokter_id', 'poli_id', 'hari', 'jam_mulai', 'jam_selesai'], 'required'],
            ['hari', 'integer', 'min' => 1, 'max' => 7],
            [['jam_mulai', 'jam_selesai'], 'time', 'format' => 'php:H:i'],
            ['jam_selesai', 'compare', 'compareAttribute' => 'jam_mulai', 'operator' => '>', 'type' => 'string', 'message' => 'Jam selesai harus setelah jam mulai.'],
            ['kuota', 'integer', 'min' => 1],
            ['kuota', 'default', 'value' => 30],
            ['is_active', 'boolean'],
            ['poli_id', 'exist', 'targetClass' => Poli::class, 'targetAttribute' => 'id'],
            ['dokter_id', 'exist', 'targetClass' => TenagaMedis::class, 'targetAttribute' => 'id', 'filter' => ['jenis' => 'dokter'], 'message' => 'Dokter tidak ditemukan.'],
            ['jam_mulai', 'validateTidakBentrok'],
        ];
    }

    public function beforeValidate(): bool
    {
        foreach (['jam_mulai', 'jam_selesai'] as $attr) {
            if (is_string($this->$attr) && strlen($this->$attr) > 5) {
                $this->$attr = substr($this->$attr, 0, 5);
            }
        }

        return parent::beforeValidate();
    }

    public function validateTidakBentrok(string $attribute): void
    {
        if ($this->hasErrors() || $this->is_active === false) {
            return;
        }

        $bentrok = self::find()
            ->where(['dokter_id' => $this->dokter_id, 'hari' => $this->hari, 'is_active' => true])
            ->andWhere(['<', 'jam_mulai', $this->jam_selesai])
            ->andWhere(['>', 'jam_selesai', $this->jam_mulai])
            ->andFilterWhere(['<>', 'id', $this->id])
            ->exists();

        if ($bentrok) {
            $this->addError($attribute, 'Jadwal dokter bentrok dengan jadwal lain di hari yang sama.');
        }
    }

    public function getDokter(): ActiveQuery
    {
        return $this->hasOne(TenagaMedis::class, ['id' => 'dokter_id']);
    }

    public function getPoli(): ActiveQuery
    {
        return $this->hasOne(Poli::class, ['id' => 'poli_id']);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'jam_mulai' => fn () => substr((string) $this->jam_mulai, 0, 5),
            'jam_selesai' => fn () => substr((string) $this->jam_selesai, 0, 5),
            'nama_hari' => fn () => self::NAMA_HARI[$this->hari] ?? null,
        ]);
    }

    public function extraFields(): array
    {
        return ['dokter', 'poli'];
    }
}
