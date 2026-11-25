<?php

namespace app\models;

class Pemeriksaan extends BaseModel
{
    public static function tableName(): string
    {
        return 'pemeriksaan';
    }

    protected static function decimalFields(): array
    {
        return ['suhu', 'berat_badan', 'tinggi_badan'];
    }

    public function optimisticLock(): string
    {
        return 'version';
    }

    public function rules(): array
    {
        return [
            [['keluhan_utama', 'anamnesis'], 'required'],
            [['keluhan_utama', 'anamnesis', 'riwayat_penyakit', 'pemeriksaan_fisik', 'rencana_terapi'], 'string'],
            [['keluhan_utama', 'anamnesis', 'riwayat_penyakit', 'pemeriksaan_fisik', 'rencana_terapi'], 'trim'],
            ['td_sistolik', 'integer', 'min' => 40, 'max' => 300],
            ['td_diastolik', 'integer', 'min' => 20, 'max' => 200],
            ['td_diastolik', 'compare', 'compareAttribute' => 'td_sistolik', 'operator' => '<', 'type' => 'number', 'skipOnEmpty' => true,
                'when' => fn () => $this->td_sistolik !== null && $this->td_sistolik !== '', 'message' => 'Diastolik harus lebih kecil dari sistolik.'],
            ['nadi', 'integer', 'min' => 20, 'max' => 250],
            ['respirasi', 'integer', 'min' => 5, 'max' => 80],
            ['suhu', 'number', 'min' => 30, 'max' => 45],
            ['spo2', 'integer', 'min' => 50, 'max' => 100],
            ['berat_badan', 'number', 'min' => 0.5, 'max' => 400],
            ['tinggi_badan', 'number', 'min' => 30, 'max' => 250],
            [['td_sistolik', 'td_diastolik', 'nadi', 'respirasi', 'suhu', 'spo2', 'berat_badan', 'tinggi_badan'], 'default', 'value' => null],
            ['version', 'integer'],
        ];
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'imt' => function () {
                if (!$this->berat_badan || !$this->tinggi_badan) {
                    return null;
                }
                $meter = $this->tinggi_badan / 100;

                return round($this->berat_badan / ($meter * $meter), 1);
            },
        ]);
    }
}
