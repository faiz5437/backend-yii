<?php

namespace app\tests\Integration;

use app\components\http\HttpCode;
use app\models\Pembayaran;
use app\models\Tagihan;
use app\services\PembayaranService;
use app\services\PendaftaranService;
use Ramsey\Uuid\Uuid;

final class PembayaranServiceTest extends TestCase
{
    private function tagihan(): Tagihan
    {
        $kunjungan = (new PendaftaranService())->daftar(['pasien_id' => $this->buatPasien()->id, 'jadwal_id' => $this->jadwalHariIni()->id], null);

        return Tagihan::findOne(['kunjungan_id' => $kunjungan->id]);
    }

    public function testRetryDenganIdempotencyKeySamaTidakMencatatPembayaranGanda(): void
    {
        $tagihan = $this->tagihan();
        $key = Uuid::uuid4()->toString();
        $service = new PembayaranService();
        $data = ['metode' => 'tunai', 'jumlah' => 10000, 'uang_diterima' => 10000];

        [$pertama, $replayPertama] = $service->bayar($tagihan->id, $key, $data, null);
        [$kedua, $replayKedua] = $service->bayar($tagihan->id, $key, $data, null);

        $this->assertFalse($replayPertama);
        $this->assertTrue($replayKedua);
        $this->assertSame($pertama->id, $kedua->id);
        $this->assertSame(1, (int) Pembayaran::find()->where(['tagihan_id' => $tagihan->id])->count());
        $this->assertSame(10000.0, (float) Tagihan::findOne($tagihan->id)->total_dibayar);
    }

    public function testPembayaranBertahapMengubahStatusSampaiLunas(): void
    {
        $tagihan = $this->tagihan();
        $service = new PembayaranService();
        $total = (float) $tagihan->total_tagihan;

        $service->bayar($tagihan->id, Uuid::uuid4()->toString(), ['metode' => 'debit', 'jumlah' => $total - 5000], null);
        $this->assertSame('sebagian', Tagihan::findOne($tagihan->id)->status);

        $service->bayar($tagihan->id, Uuid::uuid4()->toString(), ['metode' => 'debit', 'jumlah' => 5000], null);
        $this->assertSame('lunas', Tagihan::findOne($tagihan->id)->status);

        $this->assertApiException(HttpCode::CONFLICT, fn () => $service->bayar($tagihan->id, Uuid::uuid4()->toString(), ['metode' => 'debit', 'jumlah' => 1000], null));
    }

    public function testPembayaranMelebihiSisaDitolak(): void
    {
        $tagihan = $this->tagihan();

        $this->assertApiException(HttpCode::UNPROCESSABLE, fn () => (new PembayaranService())->bayar(
            $tagihan->id,
            Uuid::uuid4()->toString(),
            ['metode' => 'debit', 'jumlah' => (float) $tagihan->total_tagihan + 1],
            null
        ));
    }

    public function testPembayaranQrisMenungguKonfirmasiDanKonfirmasiIdempotent(): void
    {
        $tagihan = $this->tagihan();
        $service = new PembayaranService();

        [$pembayaran] = $service->bayar($tagihan->id, Uuid::uuid4()->toString(), ['metode' => 'qris', 'jumlah' => (float) $tagihan->total_tagihan], null);
        $this->assertSame('pending', $pembayaran->status);
        $this->assertSame('belum_bayar', Tagihan::findOne($tagihan->id)->status);

        $service->konfirmasi($pembayaran->id);
        [, $replay] = $service->konfirmasi($pembayaran->id);

        $this->assertTrue($replay);
        $this->assertSame((float) $tagihan->total_tagihan, (float) Tagihan::findOne($tagihan->id)->total_dibayar);
    }

    public function testIdempotencyKeyTidakBolehDipakaiUntukTagihanLain(): void
    {
        $key = Uuid::uuid4()->toString();
        $service = new PembayaranService();

        $service->bayar($this->tagihan()->id, $key, ['metode' => 'debit', 'jumlah' => 1000], null);

        $this->assertApiException(HttpCode::CONFLICT, fn () => $service->bayar($this->tagihan()->id, $key, ['metode' => 'debit', 'jumlah' => 1000], null));
    }
}
