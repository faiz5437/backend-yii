<?php

namespace app\services;

use app\components\Form;
use app\components\DbError;
use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\JadwalPraktik;
use app\models\Kunjungan;
use app\models\Pasien;
use app\models\TagihanItem;
use Yii;
use yii\db\Expression;

final class PendaftaranService
{
    public function __construct(private readonly BillingService $billing = new BillingService())
    {
    }

    /**
     * D1: pencegahan pendaftaran ganda
     *  - baris jadwal dikunci (FOR UPDATE) sehingga cek kuota tidak bocor saat request bersamaan
     *  - nomor antrean diambil dari counter atomik next_antrean()
     *  - partial unique index uq_kunjungan_aktif menolak kunjungan ganda di level database
     */
    public function daftar(array $data, ?string $userId, string $sumber = Kunjungan::SUMBER_LOKET): Kunjungan
    {
        $form = Form::validate($data, [
            [['pasien_id', 'jadwal_id'], 'required'],
            [['pasien_id', 'jadwal_id'], 'match', 'pattern' => '/^[0-9a-f-]{36}$/i'],
            ['tanggal', 'default', 'value' => date('Y-m-d')],
            ['tanggal', 'date', 'format' => 'php:Y-m-d'],
            ['keluhan_awal', 'string', 'max' => 1000],
        ]);

        if ($form->tanggal < date('Y-m-d')) {
            throw ApiException::unprocessable(Message::TANGGAL_LAMPAU, ['tanggal' => [Message::TANGGAL_LAMPAU]]);
        }

        $pasien = Pasien::findOne(['id' => $form->pasien_id, 'deleted_at' => null]) ?? throw ApiException::notFound('Pasien tidak ditemukan');

        try {
            return Yii::$app->db->transaction(function () use ($form, $pasien, $userId, $sumber) {
                $jadwal = JadwalPraktik::findForUpdate($form->jadwal_id) ?? throw ApiException::notFound('Jadwal praktik tidak ditemukan');

                $hari = (int) date('N', strtotime($form->tanggal));
                if (!$jadwal->is_active || (int) $jadwal->hari !== $hari) {
                    throw ApiException::unprocessable(Message::JADWAL_TIDAK_SESUAI, ['jadwal_id' => [Message::JADWAL_TIDAK_SESUAI]]);
                }

                $terisi = Kunjungan::find()
                    ->where(['jadwal_id' => $jadwal->id, 'tanggal_kunjungan' => $form->tanggal])
                    ->andWhere(['<>', 'status', Kunjungan::STATUS_BATAL])
                    ->count();

                if ($terisi >= $jadwal->kuota) {
                    throw ApiException::conflict(Message::KUOTA_PENUH);
                }

                $kunjungan = new Kunjungan([
                    'no_kunjungan' => NomorService::noKunjungan($form->tanggal),
                    'pasien_id' => $pasien->id,
                    'poli_id' => $jadwal->poli_id,
                    'dokter_id' => $jadwal->dokter_id,
                    'jadwal_id' => $jadwal->id,
                    'tanggal_kunjungan' => $form->tanggal,
                    'no_antrean' => NomorService::noAntrean($jadwal->poli_id, $form->tanggal),
                    'keluhan_awal' => $form->keluhan_awal,
                    'didaftarkan_oleh' => $userId,
                    'sumber_daftar' => $sumber,
                ]);
                $kunjungan->save(false);

                $this->billing->buatTagihan($kunjungan);
                $kunjungan->refresh();

                return $kunjungan;
            });
        } catch (\Throwable $e) {
            if (DbError::isUniqueViolation($e, 'uq_kunjungan_aktif')) {
                throw ApiException::conflict(Message::PASIEN_SUDAH_TERDAFTAR);
            }
            throw $e;
        }
    }

    public function panggil(Kunjungan $kunjungan): Kunjungan
    {
        $updated = Kunjungan::updateAll(
            ['status' => Kunjungan::STATUS_DIPANGGIL, 'dipanggil_at' => new Expression('NOW()')],
            ['id' => $kunjungan->id, 'status' => [Kunjungan::STATUS_MENUNGGU, Kunjungan::STATUS_DIPANGGIL]]
        );

        if ($updated === 0) {
            throw ApiException::conflict(Message::KUNJUNGAN_STATUS_INVALID);
        }

        $kunjungan->refresh();

        return $kunjungan;
    }

    public function batal(Kunjungan $kunjungan, string $alasan, ?string $userId): Kunjungan
    {
        return Yii::$app->db->transaction(function () use ($kunjungan, $alasan, $userId) {
            $tagihan = $this->billing->lockTagihan($kunjungan->id);

            if ((float) $tagihan->total_dibayar > 0) {
                throw ApiException::conflict(Message::KUNJUNGAN_SUDAH_DIBAYAR);
            }

            $updated = Kunjungan::updateAll(
                ['status' => Kunjungan::STATUS_BATAL, 'alasan_batal' => $alasan],
                ['id' => $kunjungan->id, 'status' => [Kunjungan::STATUS_MENUNGGU, Kunjungan::STATUS_DIPANGGIL]]
            );

            if ($updated === 0) {
                throw ApiException::conflict(Message::KUNJUNGAN_STATUS_INVALID);
            }

            foreach ($tagihan->items as $item) {
                if ($item->status === 'aktif') {
                    $this->billing->voidItem($kunjungan->id, $item->sumber_tipe, $item->sumber_id, 'Kunjungan dibatalkan: ' . $alasan, $userId, false);
                }
            }

            $kunjungan->refresh();

            return $kunjungan;
        });
    }
}
