<?php

namespace app\tests\Integration;

use app\components\http\HttpCode;
use app\models\Tagihan;
use app\models\TagihanItem;
use app\models\Tindakan;
use app\services\PembayaranService;
use app\services\PemeriksaanService;
use app\services\PendaftaranService;
use Ramsey\Uuid\Uuid;

final class BillingServiceTest extends TestCase
{
    public function testBatalTindakanMembuatItemVoidDanTotalDihitungUlang(): void
    {
        $kunjungan = (new PendaftaranService())->daftar(['pasien_id' => $this->buatPasien()->id, 'jadwal_id' => $this->jadwalHariIni()->id], null);
        $tindakan = Tindakan::findOne(['kode' => 'KNS-01']);
        $service = new PemeriksaanService();

        $item = $service->tambahTindakan($kunjungan, ['tindakan_id' => $tindakan->id]);
        $totalSetelahTindakan = (float) Tagihan::findOne(['kunjungan_id' => $kunjungan->id])->total_tagihan;

        $service->batalTindakan($kunjungan, $item->id, 'Salah input', null, false);

        $tagihan = Tagihan::findOne(['kunjungan_id' => $kunjungan->id]);
        $this->assertSame($totalSetelahTindakan - (float) $tindakan->tarif, (float) $tagihan->total_tagihan);
        $this->assertSame('void', TagihanItem::findOne(['sumber_id' => $item->id])->status);
    }

    public function testKoreksiTagihanLunasButuhOtorisasi(): void
    {
        $kunjungan = (new PendaftaranService())->daftar(['pasien_id' => $this->buatPasien()->id, 'jadwal_id' => $this->jadwalHariIni()->id], null);
        $service = new PemeriksaanService();
        $item = $service->tambahTindakan($kunjungan, ['tindakan_id' => Tindakan::findOne(['kode' => 'KNS-01'])->id]);

        $tagihan = Tagihan::findOne(['kunjungan_id' => $kunjungan->id]);
        (new PembayaranService())->bayar($tagihan->id, Uuid::uuid4()->toString(), ['metode' => 'debit', 'jumlah' => (float) $tagihan->total_tagihan], null);

        $this->assertApiException(HttpCode::FORBIDDEN, fn () => $service->batalTindakan($kunjungan, $item->id, 'Koreksi', null, false));
        $this->assertApiException(HttpCode::CONFLICT, fn () => $service->batalTindakan($kunjungan, $item->id, 'Koreksi', null, true));
    }
}
