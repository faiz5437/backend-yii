<?php

namespace app\services;

use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\Kunjungan;
use app\models\Tagihan;
use app\models\TagihanItem;
use yii\db\Expression;

/**
 * Semua perubahan tagihan lewat service ini.
 * Item tagihan tidak pernah diubah atau dihapus: koreksi dilakukan dengan void lalu item baru,
 * dan total selalu dihitung ulang dari item yang aktif.
 * Pemanggil wajib menjalankan method ini di dalam transaksi.
 */
final class BillingService
{
    public function buatTagihan(Kunjungan $kunjungan): Tagihan
    {
        $tagihan = new Tagihan([
            'no_invoice' => NomorService::noInvoice(),
            'kunjungan_id' => $kunjungan->id,
        ]);
        $tagihan->save(false);

        $poli = $kunjungan->poli;
        $this->tambahItem($kunjungan->id, TagihanItem::SUMBER_PENDAFTARAN, $kunjungan->id, 'Biaya pendaftaran ' . $poli->nama, 1, (float) $poli->tarif_pendaftaran);

        $tagihan->refresh();

        return $tagihan;
    }

    public function tambahItem(string $kunjunganId, string $sumberTipe, string $sumberId, string $deskripsi, int $qty, float $hargaSatuan): TagihanItem
    {
        $tagihan = $this->lockTagihan($kunjunganId);

        $item = new TagihanItem([
            'tagihan_id' => $tagihan->id,
            'sumber_tipe' => $sumberTipe,
            'sumber_id' => $sumberId,
            'deskripsi' => $deskripsi,
            'qty' => $qty,
            'harga_satuan' => $hargaSatuan,
            'subtotal' => round($qty * $hargaSatuan, 2),
        ]);
        $item->save(false);

        $this->hitungUlang($tagihan);

        return $item;
    }

    public function voidItem(string $kunjunganId, string $sumberTipe, string $sumberId, string $alasan, ?string $userId, bool $bolehKoreksiLunas): void
    {
        $tagihan = $this->lockTagihan($kunjunganId);

        if ($tagihan->status === Tagihan::STATUS_LUNAS && !$bolehKoreksiLunas) {
            throw ApiException::forbidden(Message::TAGIHAN_TERKUNCI);
        }

        $item = TagihanItem::findOne(['tagihan_id' => $tagihan->id, 'sumber_tipe' => $sumberTipe, 'sumber_id' => $sumberId, 'status' => 'aktif']);
        if (!$item) {
            return;
        }

        $totalBaru = (float) $tagihan->total_tagihan - (float) $item->subtotal;
        if ($totalBaru < (float) $tagihan->total_dibayar) {
            throw ApiException::conflict(Message::TAGIHAN_PERLU_REFUND);
        }

        $item->updateAttributes([
            'status' => 'void',
            'void_alasan' => $alasan,
            'void_oleh' => $userId,
            'void_at' => new Expression('NOW()'),
        ]);

        $this->hitungUlang($tagihan);
    }

    public function terapkanPembayaran(Tagihan $tagihan, float $jumlah): void
    {
        $tagihan->total_dibayar = round((float) $tagihan->total_dibayar + $jumlah, 2);
        $tagihan->status = Tagihan::statusUntuk((float) $tagihan->total_tagihan, (float) $tagihan->total_dibayar);
        $tagihan->version++;
        $tagihan->save(false, ['total_dibayar', 'status', 'version']);
    }

    public function lockTagihan(string $kunjunganId): Tagihan
    {
        $tagihan = Tagihan::findBySql('SELECT * FROM tagihan WHERE kunjungan_id = :id FOR UPDATE', [':id' => $kunjunganId])->one();

        return $tagihan ?? throw ApiException::notFound();
    }

    private function hitungUlang(Tagihan $tagihan): void
    {
        $total = (float) TagihanItem::find()->where(['tagihan_id' => $tagihan->id, 'status' => 'aktif'])->sum('subtotal');

        $tagihan->total_tagihan = round($total, 2);
        $tagihan->status = Tagihan::statusUntuk($total, (float) $tagihan->total_dibayar);
        $tagihan->version++;
        $tagihan->save(false, ['total_tagihan', 'status', 'version']);
    }
}
