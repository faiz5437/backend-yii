<?php

namespace app\models;

use app\services\NomorService;
use yii\db\ActiveQuery;

class Pasien extends BaseModel
{
    public static function tableName(): string
    {
        return 'pasien';
    }

    public function rules(): array
    {
        return [
            [['nama', 'jenis_kelamin', 'tgl_lahir'], 'required'],
            [['nik', 'nama', 'tempat_lahir', 'alamat', 'no_hp', 'alergi'], 'trim'],
            ['nik', 'match', 'pattern' => '/^[0-9]{16}$/', 'message' => 'NIK harus 16 digit angka.'],
            ['nik', 'unique', 'filter' => ['deleted_at' => null], 'message' => 'NIK sudah terdaftar atas pasien lain.'],
            ['nik', 'default', 'value' => null],
            ['nama', 'string', 'min' => 3, 'max' => 150],
            ['jenis_kelamin', 'in', 'range' => ['L', 'P']],
            ['tempat_lahir', 'string', 'max' => 100],
            ['tgl_lahir', 'date', 'format' => 'php:Y-m-d', 'max' => date('Y-m-d'), 'tooBig' => 'Tanggal lahir tidak boleh melebihi hari ini.'],
            ['no_hp', 'match', 'pattern' => '/^[0-9+]{8,20}$/', 'message' => 'Nomor HP tidak valid.'],
            ['golongan_darah', 'in', 'range' => ['A', 'B', 'AB', 'O']],
            ['golongan_darah', 'default', 'value' => null],
            [['alamat', 'alergi'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'nik' => 'NIK',
            'no_rm' => 'No. RM',
            'nama' => 'Nama',
            'jenis_kelamin' => 'Jenis kelamin',
            'tgl_lahir' => 'Tanggal lahir',
            'tempat_lahir' => 'Tempat lahir',
            'no_hp' => 'Nomor HP',
            'golongan_darah' => 'Golongan darah',
        ];
    }

    public function beforeSave($insert): bool
    {
        if ($insert && empty($this->no_rm)) {
            $this->no_rm = NomorService::noRekamMedis();
        }

        return parent::beforeSave($insert);
    }

    public function getKunjungan(): ActiveQuery
    {
        return $this->hasMany(Kunjungan::class, ['pasien_id' => 'id'])->orderBy(['tanggal_kunjungan' => SORT_DESC]);
    }

    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['deleted_at']);

        return array_merge($fields, [
            'umur' => fn () => $this->tgl_lahir ? (new \DateTime($this->tgl_lahir))->diff(new \DateTime())->y : null,
        ]);
    }
}
