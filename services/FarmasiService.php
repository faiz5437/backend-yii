<?php

namespace app\services;

use app\components\Form;
use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\Kunjungan;
use app\models\Obat;
use app\models\Resep;
use app\models\ResepItem;
use app\models\StokMutasi;
use app\models\TagihanItem;
use Yii;
use yii\db\Expression;

final class FarmasiService
{
    public function __construct(private readonly BillingService $billing = new BillingService())
    {
    }

    public function buatResep(Kunjungan $kunjungan, array $data): Resep
    {
        $items = $data['items'] ?? [];
        $errors = [];

        if (!is_array($items) || count($items) === 0) {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, ['items' => ['Minimal satu obat harus diisi.']]);
        }

        $obatIds = array_column($items, 'obat_id');
        if (count($obatIds) !== count(array_unique($obatIds))) {
            $errors['items'][] = 'Obat yang sama tidak boleh ditulis dua kali.';
        }

        $obatMap = Obat::find()->where(['id' => $obatIds, 'is_active' => true])->indexBy('id')->all();

        foreach ($items as $i => $item) {
            $form = Form::make($item, [
                [['obat_id', 'dosis', 'frekuensi', 'jumlah'], 'required'],
                [['dosis', 'frekuensi'], 'string', 'max' => 50],
                ['aturan_pakai', 'string', 'max' => 100],
                ['jumlah', 'integer', 'min' => 1, 'max' => 1000],
            ]);

            if ($form->hasErrors()) {
                $errors["items.{$i}"] = $form->getFirstErrors();
                continue;
            }

            $obat = $obatMap[$form->obat_id] ?? null;
            if (!$obat) {
                $errors["items.{$i}"] = ['obat_id' => 'Obat tidak ditemukan atau tidak aktif.'];
            } elseif ((int) $form->jumlah > $obat->stok) {
                $errors["items.{$i}"] = ['jumlah' => "Stok {$obat->nama} hanya tersisa {$obat->stok} {$obat->satuan}."];
            }
        }

        if ($errors) {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, $errors);
        }

        return Yii::$app->db->transaction(function () use ($kunjungan, $data, $items, $obatMap) {
            $resep = new Resep([
                'no_resep' => NomorService::noResep(),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $kunjungan->dokter_id,
                'catatan' => $data['catatan'] ?? null,
            ]);
            $resep->save(false);

            foreach ($items as $item) {
                (new ResepItem([
                    'resep_id' => $resep->id,
                    'obat_id' => $item['obat_id'],
                    'dosis' => $item['dosis'],
                    'frekuensi' => $item['frekuensi'],
                    'aturan_pakai' => $item['aturan_pakai'] ?? null,
                    'jumlah' => (int) $item['jumlah'],
                    'harga_satuan' => $obatMap[$item['obat_id']]->harga_jual,
                ]))->save(false);
            }

            $resep->refresh();

            return $resep;
        });
    }

    public function proses(string $resepId, ?string $userId): void
    {
        $this->ubahStatus($resepId, Resep::STATUS_DIAJUKAN, [
            'status' => Resep::STATUS_DIPROSES,
            'diproses_oleh' => $userId,
            'diproses_at' => new Expression('NOW()'),
        ]);
    }

    public function batal(string $resepId): void
    {
        $this->ubahStatus($resepId, [Resep::STATUS_DIAJUKAN, Resep::STATUS_DIPROSES], ['status' => Resep::STATUS_BATAL]);
    }

    /**
     * D2: konkurensi stok obat
     *  - status resep diubah dengan syarat status = 'diproses', sehingga resep tidak bisa diserahkan dua kali
     *  - obat dikunci berurutan menurut id agar dua transaksi tidak saling menunggu (deadlock)
     *  - stok dikurangi dengan UPDATE ... WHERE stok >= qty; jika tidak ada baris yang berubah, stok tidak cukup
     *  - CHECK (stok >= 0) di tabel obat menjadi pengaman terakhir
     * Seluruh langkah berada dalam satu transaksi, jadi bila satu obat gagal, semua dibatalkan.
     */
    public function serahkan(string $resepId, ?string $userId): Resep
    {
        return Yii::$app->db->transaction(function () use ($resepId, $userId) {
            $db = Yii::$app->db;

            $this->ubahStatus($resepId, Resep::STATUS_DIPROSES, [
                'status' => Resep::STATUS_DISERAHKAN,
                'diserahkan_oleh' => $userId,
                'diserahkan_at' => new Expression('NOW()'),
            ]);

            $resep = Resep::findOne($resepId);
            $items = ResepItem::find()->where(['resep_id' => $resepId])->orderBy(['obat_id' => SORT_ASC])->all();

            foreach ($items as $item) {
                $obat = $db->createCommand(
                    'UPDATE obat SET stok = stok - :qty WHERE id = :id AND stok >= :min RETURNING nama, stok, harga_jual',
                    [':qty' => $item->jumlah, ':min' => $item->jumlah, ':id' => $item->obat_id]
                )->queryOne();

                if ($obat === false) {
                    $sisa = Obat::findOne($item->obat_id);
                    throw ApiException::unprocessable(Message::STOK_TIDAK_CUKUP, [
                        'obat' => ["Stok {$sisa->nama} tersisa {$sisa->stok} {$sisa->satuan}, dibutuhkan {$item->jumlah}."],
                    ]);
                }

                $item->updateAttributes(['harga_satuan' => $obat['harga_jual']]);

                $this->catatMutasi($item->obat_id, 'keluar', $item->jumlah, (int) $obat['stok'] + $item->jumlah, (int) $obat['stok'], 'resep', $resepId, $userId);

                $this->billing->tambahItem(
                    $resep->kunjungan_id,
                    TagihanItem::SUMBER_OBAT,
                    $item->id,
                    $obat['nama'],
                    $item->jumlah,
                    (float) $obat['harga_jual']
                );
            }

            $resep->refresh();

            return $resep;
        });
    }

    public function sesuaikanStok(string $obatId, string $tipe, int $qty, ?string $keterangan, ?string $userId): Obat
    {
        if ($qty <= 0) {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, ['qty' => ['Jumlah harus lebih dari 0.']]);
        }

        return Yii::$app->db->transaction(function () use ($obatId, $tipe, $qty, $keterangan, $userId) {
            $obat = Obat::findForUpdate($obatId) ?? throw ApiException::notFound();
            $sebelum = (int) $obat->stok;
            $sesudah = $tipe === 'masuk' ? $sebelum + $qty : $sebelum - $qty;

            if ($sesudah < 0) {
                throw ApiException::unprocessable(Message::STOK_TIDAK_CUKUP, ['qty' => ["Stok saat ini {$sebelum} {$obat->satuan}."]]);
            }

            $obat->updateAttributes(['stok' => $sesudah]);
            $this->catatMutasi($obat->id, $tipe, $qty, $sebelum, $sesudah, 'manual', null, $userId, $keterangan);

            return $obat;
        });
    }

    private function ubahStatus(string $resepId, string|array $dari, array $perubahan): void
    {
        $updated = Resep::updateAll($perubahan, ['id' => $resepId, 'status' => $dari]);

        if ($updated === 0) {
            $resep = Resep::findOne($resepId) ?? throw ApiException::notFound();
            throw ApiException::conflict(Message::RESEP_STATUS_INVALID, ['status' => ["Status resep saat ini: {$resep->status}."]]);
        }
    }

    private function catatMutasi(string $obatId, string $tipe, int $qty, int $sebelum, int $sesudah, string $refTipe, ?string $refId, ?string $userId, ?string $keterangan = null): void
    {
        (new StokMutasi([
            'obat_id' => $obatId,
            'tipe' => $tipe,
            'qty' => $qty,
            'stok_sebelum' => $sebelum,
            'stok_sesudah' => $sesudah,
            'referensi_tipe' => $refTipe,
            'referensi_id' => $refId,
            'user_id' => $userId,
            'keterangan' => $keterangan,
        ]))->save(false);
    }
}
