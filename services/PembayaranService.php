<?php

namespace app\services;

use app\components\DbError;
use app\components\Form;
use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\Pembayaran;
use app\models\Tagihan;
use Ramsey\Uuid\Uuid;
use Yii;

final class PembayaranService
{
    public function __construct(private readonly BillingService $billing = new BillingService())
    {
    }

    /**
     * D3: pencegahan pembayaran ganda
     *  - frontend membuat Idempotency-Key (UUID) sekali per form, dan memakai key yang sama saat retry
     *  - jika key sudah tercatat, pembayaran lama dikembalikan tanpa membuat transaksi baru
     *  - baris tagihan dikunci (FOR UPDATE) agar dua kasir tidak menghitung sisa secara bersamaan
     *  - UNIQUE(idempotency_key) dan CHECK (total_dibayar <= total_tagihan) menjadi pengaman di database
     *
     * @return array{0: Pembayaran, 1: bool} pembayaran dan penanda apakah ini hasil replay
     */
    public function bayar(string $tagihanId, ?string $idempotencyKey, array $data, ?string $kasirId): array
    {
        if (!$idempotencyKey || !Uuid::isValid($idempotencyKey)) {
            throw ApiException::badRequest(Message::IDEMPOTENCY_KEY_WAJIB);
        }

        if ($existing = $this->replay($tagihanId, $idempotencyKey)) {
            return [$existing, true];
        }

        $form = Form::validate($data, [
            [['metode', 'jumlah'], 'required'],
            ['metode', 'in', 'range' => Pembayaran::METODE],
            ['jumlah', 'number', 'min' => 1],
            ['uang_diterima', 'number', 'min' => 0],
            ['uang_diterima', 'required', 'when' => fn ($m) => $m->metode === 'tunai'],
            ['referensi_eksternal', 'string', 'max' => 100],
            ['catatan', 'string', 'max' => 500],
        ]);

        $jumlah = round((float) $form->jumlah, 2);

        if ($form->metode === 'tunai' && (float) $form->uang_diterima < $jumlah) {
            throw ApiException::unprocessable(Message::UANG_KURANG, ['uang_diterima' => [Message::UANG_KURANG]]);
        }

        try {
            $pembayaran = Yii::$app->db->transaction(function () use ($tagihanId, $idempotencyKey, $form, $jumlah, $kasirId) {
                $tagihan = Tagihan::findForUpdate($tagihanId) ?? throw ApiException::notFound();

                $pending = (float) Pembayaran::find()->where(['tagihan_id' => $tagihan->id, 'status' => Pembayaran::STATUS_PENDING])->sum('jumlah');
                $sisa = round($tagihan->sisa() - $pending, 2);

                if ($sisa <= 0) {
                    throw ApiException::conflict(Message::TAGIHAN_LUNAS);
                }

                if ($jumlah > $sisa) {
                    throw ApiException::unprocessable(Message::PEMBAYARAN_MELEBIHI_SISA, ['jumlah' => ["Sisa tagihan Rp " . number_format($sisa, 0, ',', '.')]]);
                }

                $perluKonfirmasi = in_array($form->metode, Pembayaran::METODE_PERLU_KONFIRMASI, true);

                $pembayaran = new Pembayaran([
                    'no_kwitansi' => NomorService::noKwitansi(),
                    'tagihan_id' => $tagihan->id,
                    'idempotency_key' => $idempotencyKey,
                    'metode' => $form->metode,
                    'jumlah' => $jumlah,
                    'uang_diterima' => $form->metode === 'tunai' ? $form->uang_diterima : $jumlah,
                    'kembalian' => $form->metode === 'tunai' ? round((float) $form->uang_diterima - $jumlah, 2) : 0,
                    'referensi_eksternal' => $form->metode === 'tunai' ? null : ($form->referensi_eksternal ?: 'SIM-' . strtoupper(substr(str_replace('-', '', $idempotencyKey), -12))),
                    'status' => $perluKonfirmasi ? Pembayaran::STATUS_PENDING : Pembayaran::STATUS_BERHASIL,
                    'kasir_id' => $kasirId,
                    'catatan' => $form->catatan,
                ]);
                $pembayaran->save(false);

                if (!$perluKonfirmasi) {
                    $this->billing->terapkanPembayaran($tagihan, $jumlah);
                }

                return $pembayaran;
            });
        } catch (\Throwable $e) {
            if (DbError::isUniqueViolation($e, 'idempotency_key') && ($existing = $this->replay($tagihanId, $idempotencyKey))) {
                return [$existing, true];
            }
            throw $e;
        }

        $pembayaran->refresh();

        return [$pembayaran, false];
    }

    /**
     * Simulasi callback payment gateway untuk QRIS/transfer. Aman dipanggil berulang kali.
     */
    public function konfirmasi(string $pembayaranId): array
    {
        return Yii::$app->db->transaction(function () use ($pembayaranId) {
            $pembayaran = Pembayaran::findForUpdate($pembayaranId) ?? throw ApiException::notFound();

            if ($pembayaran->status === Pembayaran::STATUS_BERHASIL) {
                return [$pembayaran, true];
            }

            if ($pembayaran->status === Pembayaran::STATUS_GAGAL) {
                throw ApiException::conflict(Message::PEMBAYARAN_GAGAL);
            }

            $tagihan = Tagihan::findForUpdate($pembayaran->tagihan_id);
            $pembayaran->updateAttributes(['status' => Pembayaran::STATUS_BERHASIL]);
            $this->billing->terapkanPembayaran($tagihan, (float) $pembayaran->jumlah);

            return [$pembayaran, false];
        });
    }

    private function replay(string $tagihanId, string $idempotencyKey): ?Pembayaran
    {
        $existing = Pembayaran::findOne(['idempotency_key' => $idempotencyKey]);

        if ($existing && $existing->tagihan_id !== $tagihanId) {
            throw ApiException::conflict(Message::IDEMPOTENCY_KEY_BENTROK);
        }

        return $existing;
    }
}
