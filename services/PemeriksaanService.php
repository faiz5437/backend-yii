<?php

namespace app\services;

use app\components\Form;
use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\Diagnosis;
use app\models\Icd10;
use app\models\Kunjungan;
use app\models\KunjunganTindakan;
use app\models\Pemeriksaan;
use app\models\TagihanItem;
use app\models\Tindakan;
use Yii;
use yii\db\Expression;

final class PemeriksaanService
{
    public function __construct(private readonly BillingService $billing = new BillingService())
    {
    }

    public function pastikanDokterPemeriksa(Kunjungan $kunjungan, ?string $tenagaMedisId): void
    {
        if ($kunjungan->dokter_id !== $tenagaMedisId) {
            throw ApiException::forbidden(Message::KUNJUNGAN_BUKAN_PASIEN_ANDA);
        }

        if ($kunjungan->status === Kunjungan::STATUS_BATAL) {
            throw ApiException::conflict(Message::KUNJUNGAN_STATUS_INVALID);
        }
    }

    /**
     * D4: kolom version dipakai sebagai optimistic lock. Jika versi yang dikirim sudah usang,
     * Yii melempar StaleObjectException dan API membalas 409.
     */
    public function simpanPemeriksaan(Kunjungan $kunjungan, array $data): Pemeriksaan
    {
        return Yii::$app->db->transaction(function () use ($kunjungan, $data) {
            $pemeriksaan = $kunjungan->pemeriksaan ?? new Pemeriksaan([
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $kunjungan->dokter_id,
            ]);

            $pemeriksaan->load($data, '');

            if (!$pemeriksaan->save()) {
                throw ApiException::fromModel($pemeriksaan);
            }

            if (in_array($kunjungan->status, [Kunjungan::STATUS_MENUNGGU, Kunjungan::STATUS_DIPANGGIL], true)) {
                $kunjungan->updateAttributes([
                    'status' => Kunjungan::STATUS_DIPERIKSA,
                    'mulai_periksa_at' => new Expression('COALESCE(mulai_periksa_at, NOW())'),
                ]);
            }

            $pemeriksaan->refresh();

            return $pemeriksaan;
        });
    }

    /**
     * Daftar diagnosis disimpan utuh (replace). Riwayat perubahan tetap tercatat oleh trigger audit.
     */
    public function simpanDiagnosis(Kunjungan $kunjungan, array $items): array
    {
        $errors = [];
        $utama = 0;
        $kode = [];

        foreach ($items as $i => $item) {
            $form = Form::make($item, [
                [['icd10_kode', 'jenis'], 'required'],
                ['jenis', 'in', 'range' => ['utama', 'sekunder']],
                ['icd10_kode', 'exist', 'targetClass' => Icd10::class, 'targetAttribute' => 'kode', 'message' => 'Kode ICD-10 tidak ditemukan.'],
                ['keterangan', 'string', 'max' => 500],
            ]);

            if ($form->hasErrors()) {
                $errors["items.{$i}"] = $form->getFirstErrors();
            }

            $utama += ($item['jenis'] ?? null) === 'utama' ? 1 : 0;
            $kode[] = $item['icd10_kode'] ?? null;
        }

        if ($utama !== 1) {
            $errors['items'] = [Message::DIAGNOSIS_UTAMA_WAJIB];
        }

        if (count($kode) !== count(array_unique($kode))) {
            $errors['items'][] = 'Kode diagnosis tidak boleh dobel.';
        }

        if ($errors) {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, $errors);
        }

        return Yii::$app->db->transaction(function () use ($kunjungan, $items) {
            Diagnosis::deleteAll(['kunjungan_id' => $kunjungan->id]);

            foreach ($items as $item) {
                (new Diagnosis([
                    'kunjungan_id' => $kunjungan->id,
                    'icd10_kode' => strtoupper($item['icd10_kode']),
                    'jenis' => $item['jenis'],
                    'keterangan' => $item['keterangan'] ?? null,
                    'dokter_id' => $kunjungan->dokter_id,
                ]))->save(false);
            }

            return Diagnosis::find()->with('icd10')->where(['kunjungan_id' => $kunjungan->id])->orderBy(['jenis' => SORT_DESC])->all();
        });
    }

    public function tambahTindakan(Kunjungan $kunjungan, array $data): KunjunganTindakan
    {
        $form = Form::validate($data, [
            ['tindakan_id', 'required'],
            ['qty', 'default', 'value' => 1],
            ['qty', 'integer', 'min' => 1, 'max' => 100],
            ['catatan', 'string', 'max' => 500],
        ]);

        $tindakan = Tindakan::findOne(['id' => $form->tindakan_id, 'is_active' => true]) ?? throw ApiException::notFound('Tindakan tidak ditemukan');

        return Yii::$app->db->transaction(function () use ($kunjungan, $tindakan, $form) {
            $item = new KunjunganTindakan([
                'kunjungan_id' => $kunjungan->id,
                'tindakan_id' => $tindakan->id,
                'dokter_id' => $kunjungan->dokter_id,
                'qty' => (int) $form->qty,
                'tarif' => $tindakan->tarif,
                'catatan' => $form->catatan,
            ]);
            $item->save(false);

            $this->billing->tambahItem($kunjungan->id, TagihanItem::SUMBER_TINDAKAN, $item->id, $tindakan->nama, (int) $form->qty, (float) $tindakan->tarif);

            $item->refresh();

            return $item;
        });
    }

    /**
     * D4: tindakan tidak dihapus. Statusnya dibatalkan, item tagihannya di-void, lalu total dihitung ulang.
     */
    public function batalTindakan(Kunjungan $kunjungan, string $kunjunganTindakanId, string $alasan, ?string $userId, bool $bolehKoreksiLunas): KunjunganTindakan
    {
        if (trim($alasan) === '') {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, ['alasan' => ['Alasan pembatalan wajib diisi.']]);
        }

        $item = KunjunganTindakan::findOne(['id' => $kunjunganTindakanId, 'kunjungan_id' => $kunjungan->id, 'status' => 'aktif'])
            ?? throw ApiException::notFound('Tindakan tidak ditemukan');

        return Yii::$app->db->transaction(function () use ($kunjungan, $item, $alasan, $userId, $bolehKoreksiLunas) {
            $this->billing->voidItem($kunjungan->id, TagihanItem::SUMBER_TINDAKAN, $item->id, $alasan, $userId, $bolehKoreksiLunas);
            $item->updateAttributes(['status' => 'batal', 'alasan_batal' => $alasan]);

            return $item;
        });
    }

    public function selesai(Kunjungan $kunjungan): Kunjungan
    {
        $lengkap = $kunjungan->pemeriksaan !== null
            && Diagnosis::find()->where(['kunjungan_id' => $kunjungan->id, 'jenis' => 'utama'])->exists();

        if (!$lengkap) {
            throw ApiException::unprocessable(Message::PEMERIKSAAN_BELUM_LENGKAP);
        }

        $kunjungan->updateAttributes([
            'status' => Kunjungan::STATUS_SELESAI,
            'selesai_at' => new Expression('NOW()'),
        ]);
        $kunjungan->refresh();

        return $kunjungan;
    }
}
