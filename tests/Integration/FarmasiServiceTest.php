<?php

namespace app\tests\Integration;

use app\components\http\HttpCode;
use app\models\Kunjungan;
use app\models\Obat;
use app\models\Resep;
use app\models\StokMutasi;
use app\models\TagihanItem;
use app\services\FarmasiService;
use app\services\PendaftaranService;

final class FarmasiServiceTest extends TestCase
{
    private function kunjungan(): Kunjungan
    {
        return (new PendaftaranService())->daftar(['pasien_id' => $this->buatPasien()->id, 'jadwal_id' => $this->jadwalHariIni()->id], null);
    }

    public function testResepDitolakJikaJumlahMelebihiStok(): void
    {
        $obat = Obat::findOne(['kode' => 'OB-016']);

        $this->assertApiException(HttpCode::UNPROCESSABLE, fn () => (new FarmasiService())->buatResep($this->kunjungan(), [
            'items' => [['obat_id' => $obat->id, 'dosis' => '100 mg', 'frekuensi' => '2 x 1', 'jumlah' => $obat->stok + 1]],
        ]));
    }

    public function testSerahkanMengurangiStokMencatatMutasiDanMenambahTagihan(): void
    {
        $service = new FarmasiService();
        $obat = Obat::findOne(['kode' => 'OB-001']);
        $stokAwal = $obat->stok;

        $resep = $service->buatResep($this->kunjungan(), ['items' => [['obat_id' => $obat->id, 'dosis' => '500 mg', 'frekuensi' => '3 x 1', 'jumlah' => 10]]]);
        $service->proses($resep->id, null);
        $service->serahkan($resep->id, null);

        $this->assertSame($stokAwal - 10, Obat::findOne($obat->id)->stok);
        $this->assertSame('diserahkan', Resep::findOne($resep->id)->status);
        $this->assertTrue(StokMutasi::find()->where(['referensi_id' => $resep->id, 'tipe' => 'keluar', 'stok_sesudah' => $stokAwal - 10])->exists());
        $this->assertTrue(TagihanItem::find()->where(['sumber_tipe' => 'obat', 'status' => 'aktif', 'subtotal' => 10 * (float) $obat->harga_jual])->exists());
    }

    public function testStokTidakCukupSaatPenyerahanMembatalkanSeluruhTransaksi(): void
    {
        $service = new FarmasiService();
        $paracetamol = Obat::findOne(['kode' => 'OB-001']);
        $cefixime = Obat::findOne(['kode' => 'OB-016']);

        $resep = $service->buatResep($this->kunjungan(), ['items' => [
            ['obat_id' => $paracetamol->id, 'dosis' => '500 mg', 'frekuensi' => '3 x 1', 'jumlah' => 10],
            ['obat_id' => $cefixime->id, 'dosis' => '100 mg', 'frekuensi' => '2 x 1', 'jumlah' => 5],
        ]]);
        $service->proses($resep->id, null);

        // Stok habis diambil transaksi lain sebelum resep ini diserahkan
        Obat::updateAll(['stok' => 2], ['id' => $cefixime->id]);

        $this->assertApiException(HttpCode::UNPROCESSABLE, fn () => $service->serahkan($resep->id, null));

        $this->assertSame($paracetamol->stok, Obat::findOne($paracetamol->id)->stok, 'Stok paracetamol harus kembali seperti semula.');
        $this->assertSame(2, Obat::findOne($cefixime->id)->stok);
        $this->assertSame('diproses', Resep::findOne($resep->id)->status);
    }

    public function testResepTidakBisaDiserahkanDuaKali(): void
    {
        $service = new FarmasiService();
        $obat = Obat::findOne(['kode' => 'OB-001']);
        $resep = $service->buatResep($this->kunjungan(), ['items' => [['obat_id' => $obat->id, 'dosis' => '500 mg', 'frekuensi' => '3 x 1', 'jumlah' => 5]]]);
        $service->proses($resep->id, null);
        $service->serahkan($resep->id, null);

        $this->assertApiException(HttpCode::CONFLICT, fn () => $service->serahkan($resep->id, null));
        $this->assertSame($obat->stok - 5, Obat::findOne($obat->id)->stok);
    }
}
