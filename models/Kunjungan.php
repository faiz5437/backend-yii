<?php

namespace app\models;

use yii\db\ActiveQuery;

class Kunjungan extends BaseModel
{
    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_DIPANGGIL = 'dipanggil';
    public const STATUS_DIPERIKSA = 'diperiksa';
    public const STATUS_SELESAI = 'selesai';
    public const STATUS_BATAL = 'batal';

    public const SUMBER_LOKET = 'loket';
    public const SUMBER_ANJUNGAN = 'anjungan';

    public static function tableName(): string
    {
        return 'kunjungan';
    }

    public function getPasien(): ActiveQuery
    {
        return $this->hasOne(Pasien::class, ['id' => 'pasien_id']);
    }

    public function getPoli(): ActiveQuery
    {
        return $this->hasOne(Poli::class, ['id' => 'poli_id']);
    }

    public function getDokter(): ActiveQuery
    {
        return $this->hasOne(TenagaMedis::class, ['id' => 'dokter_id']);
    }

    public function getJadwal(): ActiveQuery
    {
        return $this->hasOne(JadwalPraktik::class, ['id' => 'jadwal_id']);
    }

    public function getPemeriksaan(): ActiveQuery
    {
        return $this->hasOne(Pemeriksaan::class, ['kunjungan_id' => 'id']);
    }

    public function getDiagnosis(): ActiveQuery
    {
        return $this->hasMany(Diagnosis::class, ['kunjungan_id' => 'id'])->orderBy(['jenis' => SORT_DESC, 'created_at' => SORT_ASC]);
    }

    public function getTindakan(): ActiveQuery
    {
        return $this->hasMany(KunjunganTindakan::class, ['kunjungan_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function getResep(): ActiveQuery
    {
        return $this->hasMany(Resep::class, ['kunjungan_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function getLabOrders(): ActiveQuery
    {
        return $this->hasMany(LabOrder::class, ['kunjungan_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }

    public function getTagihan(): ActiveQuery
    {
        return $this->hasOne(Tagihan::class, ['kunjungan_id' => 'id']);
    }

    public function getPendaftar(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'didaftarkan_oleh']);
    }

    public function isAktif(): bool
    {
        return !in_array($this->status, [self::STATUS_BATAL, self::STATUS_SELESAI], true);
    }

    public function kodeAntrean(): string
    {
        return ($this->poli->kode ?? 'A') . '-' . str_pad((string) $this->no_antrean, 3, '0', STR_PAD_LEFT);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'kode_antrean' => fn () => $this->kodeAntrean(),
        ]);
    }

    public function extraFields(): array
    {
        return [
            'pasien', 'poli', 'dokter', 'jadwal', 'pemeriksaan', 'diagnosis', 'tindakan', 'resep', 'labOrders', 'tagihan',
            'pendaftar' => fn () => $this->pendaftar ? ['id' => $this->pendaftar->id, 'nama' => $this->pendaftar->nama] : null,
        ];
    }
}
